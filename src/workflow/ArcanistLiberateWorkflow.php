<?php

final class ArcanistLiberateWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'liberate';
  }

  public function getWorkflowInformation() {
    // TOOLSETS: Expand this help.

    $help = pht(<<<EOTEXT
Create or update an Arcanist library.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(
        pht('Create or update an Arcanist library.'))
      ->addExample(pht('**liberate**'))
      ->addExample(pht('**liberate** [__path__]'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('clean')
        ->setHelp(
          pht('Perform a clean rebuild, ignoring caches. Thorough, but slow.')),
      $this->newWorkflowArgument('argv')
        ->setWildcard(true)
        ->setIsPathArgument(true),
    );
  }

  protected function newPrompts() {
    return array(
      $this->newPrompt('arc.liberate.create')
        ->setDescription(
          pht(
            'Confirms creation of a new library.')),
    );
  }


  public function runWorkflow() {
    $log = $this->getLogEngine();

    $argv = $this->getArgument('argv');
    if (count($argv) > 1) {
      throw new ArcanistUsageException(
        pht(
          'Provide only one path to "arc liberate". The path should identify '.
          'a directory where you want to create or update a library.'));
    } else if (!$argv) {
      $log->writeStatus(
        pht('SCAN'),
        pht('Searching for libraries in the current working directory...'));

      $init_files = id(new FileFinder(getcwd()))
        ->withPath('*/__phutil_library_init__.php')
        ->find();

      if (!$init_files) {
        throw new ArcanistUsageException(
          pht(
            'Unable to find any libraries under the current working '.
            'directory. To create a library, provide a path.'));
      }

      $paths = array();
      foreach ($init_files as $init) {
        $paths[] = Filesystem::resolvePath(dirname($init));
      }
    } else {
      $paths = array(
        Filesystem::resolvePath(head($argv)),
      );
    }

    $any_errors = false;
    foreach ($paths as $path) {
      $log->writeStatus(
        pht('WORK'),
        pht(
          'Updating library: %s',
          Filesystem::readablePath($path).DIRECTORY_SEPARATOR));
      $exit_code = $this->liberatePath($path);
      if ($exit_code !== 0) {
        $any_errors = true;
        $log->writeError(
          pht('ERROR'),
          pht('Failed to update library: %s', $path));
      }
    }

    if (!$any_errors) {
      $log->writeSuccess(
        pht('DONE'),
        pht('Updated %s librarie(s).', phutil_count($paths)));
    }

    return 0;
  }

  /**
   * @return  int The exit code of running the rebuild-map.php script, which
   *              will be 0 to indicate success or non-zero for failure.
   */
  private function liberatePath($path) {
    if (!Filesystem::pathExists($path.'/__phutil_library_init__.php')) {
      echo tsprintf(
        "%s\n",
        pht(
          'No library currently exists at the path "%s"...',
          $path));
      $this->liberateCreateDirectory($path);
      return $this->liberateCreateLibrary($path);
    }

    $version = $this->getLibraryFormatVersion($path);
    switch ($version) {
      case 1:
        throw new ArcanistUsageException(
          pht(
            'This very old library is no longer supported.'));
      case 2:
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

  /**
   * @return  int The exit code of running the rebuild-map.php script, which
   *              will be 0 to indicate success or non-zero for failure.
   */
  private function liberateVersion2($path) {
    $bin = $this->getScriptPath('support/lib/rebuild-map.php');

    $argv = array();
    if ($this->getArgument('clean')) {
      $argv[] = '--drop-cache';
    }

    return phutil_passthru(
      'php -f %R -- %Ls %R',
      $bin,
      $argv,
      $path);
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

    echo tsprintf(
      "%!\n%W\n",
      pht('NEW LIBRARY'),
      pht(
        'The directory "%s" does not exist. Do you want to create it?',
        $path));

    $query = pht('Create new library?');

    $this->getPrompt('arc.liberate.create')
      ->setQuery($query)
      ->execute();

    execx('mkdir -p %R', $path);
  }

  /**
   * @return  int The exit code of running the rebuild-map.php script, which
   *              will be 0 to indicate success or non-zero for failure.
   */
  private function liberateCreateLibrary($path) {
    $init_path = $path.'/__phutil_library_init__.php';
    if (Filesystem::pathExists($init_path)) {
      return 0;
    }

    echo pht("Creating new libphutil library in '%s'.", $path)."\n";

    do {
      echo pht('Choose a name for the new library.')."\n";
      $name = phutil_console_prompt(
        pht('What do you want to name this library?'));

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
    return $this->liberateVersion2($path);
  }


  private function getScriptPath($script) {
    $root = dirname(phutil_get_library_root('arcanist'));
    return $root.'/'.$script;
  }

}
