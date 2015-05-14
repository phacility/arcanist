<?php

/**
 * Create and update libphutil libraries.
 *
 * This workflow is unusual and involves re-executing 'arc liberate' as a
 * subprocess with `--remap` and `--verify`. This is because there is no way
 * to unload or reload a library, so every process is stuck with the library
 * definition it had when it first loaded. This is normally fine, but
 * problematic in this case because `arc liberate` modifies library definitions.
 */
final class ArcanistLiberateWorkflow extends ArcanistWorkflow {

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
        'help' => pht(
          'Drop the module cache before liberating. This will completely '.
          'reanalyze the entire library. Thorough, but slow!'),
      ),
      'force-update' => array(
        'help' => pht(
          'Force the library map to be updated, even in the presence of '.
          'lint errors.'),
      ),
      'library-name' => array(
        'param' => 'name',
        'help' =>
          pht('Use a flag for library name rather than awaiting user input.'),
      ),
      'remap' => array(
        'hide' => true,
        'help' => pht(
          'Internal. Run the remap step of liberation. You do not need to '.
          'run this unless you are debugging the workflow.'),
      ),
      'verify' => array(
        'hide' => true,
        'help' => pht(
          'Internal. Run the verify step of liberation. You do not need to '.
          'run this unless you are debugging the workflow.'),
      ),
      'upgrade' => array(
        'hide'  => true,
        'help'  => pht('Experimental. Upgrade library to v2.'),
      ),
      '*' => 'argv',
    );
  }

  public function run() {
    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        pht(
          "Provide only one path to '%s'. The path should be a directory ".
          "where you want to create or update a libphutil library.",
          'arc liberate'));
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
          pht(
            'Specified directory contains more than one libphutil library. '.
            'Use a more specific path.'));
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
        echo pht("No library currently exists at that path...\n");
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
          pht(
            "This library is using libphutil v1, which is no ".
            "longer supported. Run '%s' to upgrade to v2.",
            'arc liberate --upgrade'));
      case 2:
        if ($this->getArgument('upgrade')) {
          throw new ArcanistUsageException(
            pht("Can't upgrade a v2 library!"));
        }
        return $this->liberateVersion2($path);
      default:
        throw new ArcanistUsageException(
          pht("Unknown library version '%s'!", $version));
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

    echo pht('Removing %s files...', '__init__.php')."\n";
    foreach ($inits as $init) {
      Filesystem::remove($path.'/'.$init);
    }

    echo pht('Upgrading library to v2...')."\n";
    $this->liberateVersion2($path);
  }

  private function liberateCreateDirectory($path) {
    if (Filesystem::pathExists($path)) {
      if (!is_dir($path)) {
        throw new ArcanistUsageException(
          pht(
            'Provide a directory to create or update a libphutil library in.'));
      }
      return;
    }

    echo pht("The directory '%s' does not exist.", $path);
    if (!phutil_console_confirm(pht('Do you want to create it?'))) {
      throw new ArcanistUsageException(pht('Canceled.'));
    }

    execx('mkdir -p %s', $path);
  }

  private function liberateCreateLibrary($path) {
    $init_path = $path.'/__phutil_library_init__.php';
    if (Filesystem::pathExists($init_path)) {
      return;
    }

    echo pht("Creating new libphutil library in '%s'.", $path)."\n";

    do {
      $name = $this->getArgument('library-name');
      if ($name === null) {
        echo pht('Choose a name for the new library.')."\n";
        $name = phutil_console_prompt(
          pht('What do you want to name this library?'));
      } else {
        echo pht('Using library name %s.', $name)."\n";
      }
      if (preg_match('/^[a-z-]+$/', $name)) {
        break;
      } else {
        echo phutil_console_format(
          "%s\n",
          pht(
          'Library name should contain only lowercase letters and hyphens.'));
      }
    } while (true);

    $template =
      "<?php\n\n".
      "phutil_register_library('{$name}', __FILE__);\n";

    echo pht(
      "Writing '%s' to '%s'...\n",
      '__phutil_library_init__.php',
      $path);
    Filesystem::writeFile($init_path, $template);
    $this->liberateVersion2($path);
  }


  private function getScriptPath($script) {
    $root = dirname(phutil_get_library_root('phutil'));
    return $root.'/'.$script;
  }

}
