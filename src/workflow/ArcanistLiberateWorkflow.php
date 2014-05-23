<?php

/**
 * Create and update libphutil libraries.
 *
 * This workflow is unusual and involves reexecuting 'arc liberate' as a
 * subprocess with "--remap" and "--verify". This is because there is no way
 * to unload or reload a library, so every process is stuck with the library
 * definition it had when it first loaded. This is normally fine, but
 * problematic in this case because 'arc liberate' modifies library definitions.
 *
 * @group workflow
 */
final class ArcanistLiberateWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'liberate';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **liberate** [__path__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: libphutil
          Create or update a libphutil library, generating required metadata
          files like \__init__.php.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'all' => array(
        'help' =>
          'Drop the module cache before liberating. This will completely '.
          'reanalyze the entire library. Thorough, but slow!',
      ),
      'force-update' => array(
        'help' =>
          'Force the library map to be updated, even in the presence of '.
          'lint errors.',
      ),
      'library-name' => array(
        'param' => 'name',
        'help' =>
          'Use a flag for library name rather than awaiting user input.',
      ),
      'remap' => array(
        'hide' => true,
        'help' =>
          'Internal. Run the remap step of liberation. You do not need to '.
          'run this unless you are debugging the workflow.',
      ),
      'verify' => array(
        'hide' => true,
        'help' =>
          'Internal. Run the verify step of liberation. You do not need to '.
          'run this unless you are debugging the workflow.',
      ),
      'upgrade' => array(
        'hide'  => true,
        'help'  => 'Experimental. Upgrade library to v2.',
      ),
      '*' => 'argv',
    );
  }

  public function run() {
    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        "Provide only one path to 'arc liberate'. The path should be a ".
        "directory where you want to create or update a libphutil library.");
    } else if (count($argv) == 0) {
      $path = getcwd();
    } else {
      $path = reset($argv);
    }

    $is_remap = $this->getArgument('remap');
    $is_verify = $this->getArgument('verify');

    $path = Filesystem::resolvePath($path);

    if (Filesystem::pathExists($path) && is_dir($path)) {
      $init = id(new FileFinder($path))
        ->withPath('*/__phutil_library_init__.php')
        ->find();
    } else {
      $init = null;
    }

    if ($init) {
      if (count($init) > 1) {
        throw new ArcanistUsageException(
          'Specified directory contains more than one libphutil library. Use '.
          'a more specific path.');
      }
      $path = Filesystem::resolvePath(dirname(reset($init)), $path);
    } else {
      $found = false;
      foreach (Filesystem::walkToRoot($path) as $dir) {
        if (Filesystem::pathExists($dir.'/__phutil_library_init__.php')) {
          $path = $dir;
          $found = true;
          break;
        }
      }
      if (!$found) {
        echo "No library currently exists at that path...\n";
        $this->liberateCreateDirectory($path);
        $this->liberateCreateLibrary($path);
        return;
      }
    }

    $version = $this->getLibraryFormatVersion($path);
    switch ($version) {
      case 1:
        if ($this->getArgument('upgrade')) {
          return $this->upgradeLibrary($path);
        }
        throw new ArcanistUsageException(
          "This library is using libphutil v1, which is no longer supported. ".
          "Run 'arc liberate --upgrade' to upgrade to v2.");
      case 2:
        if ($this->getArgument('upgrade')) {
          throw new ArcanistUsageException(
            "Can't upgrade a v2 library!");
        }
        return $this->liberateVersion2($path);
      default:
        throw new ArcanistUsageException(
          "Unknown library version '{$version}'!");
    }
  }

  private function getLibraryFormatVersion($path) {
    $map_file = $path.'/__phutil_library_map__.php';
    if (!Filesystem::pathExists($map_file)) {
      // Default to library v1.
      return 1;
    }

    $map = Filesystem::readFile($map_file);

    $matches = null;
    if (preg_match('/@phutil-library-version (\d+)/', $map, $matches)) {
      return (int)$matches[1];
    }

    return 1;
  }

  private function liberateVersion2($path) {
    $bin = $this->getScriptPath('scripts/phutil_rebuild_map.php');

    return phutil_passthru(
      'php %s %C %s',
      $bin,
      $this->getArgument('all') ? '--drop-cache' : '',
      $path);
  }

  private function upgradeLibrary($path) {
    $inits = id(new FileFinder($path))
      ->withPath('*/__init__.php')
      ->withType('f')
      ->find();

    echo "Removing __init__.php files...\n";
    foreach ($inits as $init) {
      Filesystem::remove($path.'/'.$init);
    }

    echo "Upgrading library to v2...\n";
    $this->liberateVersion2($path);
  }

  private function liberateCreateDirectory($path) {
    if (Filesystem::pathExists($path)) {
      if (!is_dir($path)) {
        throw new ArcanistUsageException(
          'Provide a directory to create or update a libphutil library in.');
      }
      return;
    }

    echo "The directory '{$path}' does not exist.";
    if (!phutil_console_confirm('Do you want to create it?')) {
      throw new ArcanistUsageException('Cancelled.');
    }

    execx('mkdir -p %s', $path);
  }

  private function liberateCreateLibrary($path) {
    $init_path = $path.'/__phutil_library_init__.php';
    if (Filesystem::pathExists($init_path)) {
      return;
    }

    echo "Creating new libphutil library in '{$path}'.\n";

    do {
      $name = $this->getArgument('library-name');
      if ($name === null) {
        echo "Choose a name for the new library.\n";
        $name = phutil_console_prompt('What do you want to name this library?');
      } else {
        echo "Using library name {$name}.\n";
      }
      if (preg_match('/^[a-z-]+$/', $name)) {
        break;
      } else {
        echo "Library name should contain only lowercase letters and ".
          "hyphens.\n";
      }
    } while (true);

    $template =
      "<?php\n\n".
      "phutil_register_library('{$name}', __FILE__);\n";

    echo "Writing '__phutil_library_init__.php' to '{$path}'...\n";
    Filesystem::writeFile($init_path, $template);
    $this->liberateVersion2($path);
  }


  private function getScriptPath($script) {
    $root = dirname(phutil_get_library_root('arcanist'));
    return $root.'/'.$script;
  }

}
