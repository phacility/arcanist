<?php

final class ArcanistRuntime {

  public function execute(array $argv) {

    try {
      $this->checkEnvironment();
    } catch (Exception $ex) {
      echo "CONFIGURATION ERROR\n\n";
      echo $ex->getMessage();
      echo "\n\n";
      return 1;
    }

    PhutilTranslator::getInstance()
      ->setLocale(PhutilLocale::loadLocale('en_US'))
      ->setTranslations(PhutilTranslation::getTranslationMapForLocale('en_US'));

    try {
      return $this->executeCore($argv);
    } catch (PhutilArgumentUsageException $ex) {
      fwrite(
        STDERR,
        tsprintf(
          "**%s:** %s\n",
          pht('Usage Exception'),
          $ex->getMessage()));

      return 77;
    }
  }

  private function executeCore(array $argv) {
    $config_args = array(
      array(
        'name' => 'library',
        'param' => 'path',
        'help' => pht('Load a library.'),
        'repeat' => true,
      ),
      array(
        'name' => 'config',
        'param' => 'key=value',
        'repeat' => true,
        'help' => pht('Specify a runtime configuration value.'),
      ),
      array(
        'name' => 'config-file',
        'param' => 'path',
        'repeat' => true,
        'help' => pht(
          'Load one or more configuration files. If this flag is provided, '.
          'the system and user configuration files are ignored.'),
      ),
    );

    $args = id(new PhutilArgumentParser($argv))
      ->parseStandardArguments();

    $is_trace = $args->getArg('trace');
    if ($is_trace) {
      $this->logTrace(pht('ARGV'), csprintf('%Ls', $argv));
    }

    $args->parsePartial($config_args, true);

    $config = $this->loadConfiguration($args);

    $this->loadLibraries($args, $config);

    $toolset = $this->newToolset($argv);

    $args->parsePartial($toolset->getToolsetArguments());

    $workflows = $this->newWorkflows($toolset);

    $phutil_workflows = array();
    foreach ($workflows as $key => $workflow) {
      $phutil_workflows[$key] = $workflow->newPhutilWorkflow();
    }

    $unconsumed_argv = $args->getUnconsumedArgumentVector();

    if (!$unconsumed_argv) {
      // TOOLSETS: This means the user just ran "arc" or some other top-level
      // toolset without any workflow argument. We should give them a summary
      // of the toolset, a list of workflows, and a pointer to "arc help" for
      // more details.

      // A possible exception is "arc --help", which should perhaps pass
      // through and act like "arc help".
      throw new PhutilArgumentUsageException(pht('Choose a workflow!'));
    }

    $result = $this->resolveAliases($workflows, $unconsumed_argv, $config);
    if (is_int($result)) {
      return $result;
    }

    $args->setUnconsumedArgumentVector($result);

    return $args->parseWorkflows($phutil_workflows);
  }


  /**
   * Perform some sanity checks against the possible diversity of PHP builds in
   * the wild, like very old versions and builds that were compiled with flags
   * that exclude core functionality.
   */
  private function checkEnvironment() {
    // NOTE: We don't have phutil_is_windows() yet here.
    $is_windows = (DIRECTORY_SEPARATOR != '/');

    // We use stream_socket_pair() which is not available on Windows earlier.
    $min_version = ($is_windows ? '5.3.0' : '5.2.3');
    $cur_version = phpversion();
    if (version_compare($cur_version, $min_version, '<')) {
      $message = sprintf(
        'You are running a version of PHP ("%s"), which is older than the '.
        'minimum supported version ("%s"). Update PHP to continue.',
        $cur_version,
        $min_version);

      throw new Exception($message);
    }

    if ($is_windows) {
      $need_functions = array(
        'curl_init'     => array('builtin-dll', 'php_curl.dll'),
      );
    } else {
      $need_functions = array(
        'curl_init'     => array(
          'text',
          "You need to install the cURL PHP extension, maybe with ".
          "'apt-get install php5-curl' or 'yum install php53-curl' or ".
          "something similar.",
        ),
        'json_decode'   => array('flag', '--without-json'),
      );
    }

    $problems = array();

    $config = null;
    $show_config = false;
    foreach ($need_functions as $fname => $resolution) {
      if (function_exists($fname)) {
        continue;
      }

      static $info;
      if ($info === null) {
        ob_start();
        phpinfo(INFO_GENERAL);
        $info = ob_get_clean();
        $matches = null;
        if (preg_match('/^Configure Command =>\s*(.*?)$/m', $info, $matches)) {
          $config = $matches[1];
        }
      }

      list($what, $which) = $resolution;

      if ($what == 'flag' && strpos($config, $which) !== false) {
        $show_config = true;
        $problems[] = sprintf(
          'The build of PHP you are running was compiled with the configure '.
          'flag "%s", which means it does not support the function "%s()". '.
          'This function is required for Arcanist to run. Install a standard '.
          'build of PHP or rebuild it without this flag. You may also be '.
          'able to build or install the relevant extension separately.',
          $which,
          $fname);
        continue;
      }

      if ($what == 'builtin-dll') {
        $problems[] = sprintf(
          'The build of PHP you are running does not have the "%s" extension '.
          'enabled. Edit your php.ini file and uncomment the line which '.
          'reads "extension=%s".',
          $which,
          $which);
        continue;
      }

      if ($what == 'text') {
        $problems[] = $which;
        continue;
      }

      $problems[] = sprintf(
        'The build of PHP you are running is missing the required function '.
        '"%s()". Rebuild PHP or install the extension which provides "%s()".',
        $fname,
        $fname);
    }

    if ($problems) {
      if ($show_config) {
        $problems[] = "PHP was built with this configure command:\n\n{$config}";
      }
      $problems = implode("\n\n", $problems);

      throw new Exception($problems);
    }
  }

  private function loadConfiguration(PhutilArgumentParser $args) {
    $engine = id(new ArcanistConfigurationEngine())
      ->setArguments($args);

    $working_copy = ArcanistWorkingCopy::newFromWorkingDirectory(getcwd());
    if ($working_copy) {
      $engine->setWorkingCopy($working_copy);
    }

    return $engine->newConfigurationSourceList();
  }

  private function loadLibraries(
    PhutilArgumentParser $args,
    ArcanistConfigurationSourceList $config) {

    // TOOLSETS: Make this work again -- or replace it entirely with package
    // management?
    return;

    $is_trace = $args->getArg('trace');

    $load = array();
    $working_copy = $this->getWorkingCopy();

    $cli_libraries = $args->getArg('library');
    if ($cli_libraries) {
      $load[] = array(
        '--library',
        $cli_libraries,
      );
    } else {
      $system_config = $config->readSystemArcConfig();
      $load[] = array(
        $config->getSystemArcConfigLocation(),
        idx($system_config, 'load', array()),
      );

      $global_config = $config->readUserArcConfig();
      $load[] = array(
        $config->getUserConfigurationFileLocation(),
        idx($global_config, 'load', array()),
      );

      $load[] = array(
        '.arcconfig',
        $working_copy->getProjectConfig('load'),
      );

      $load[] = array(
        // TODO: We could explain exactly where this is coming from more
        // clearly.
        './.../arc/config',
        $working_copy->getLocalConfig('load'),
      );

      $load[] = array(
        '--config load=...',
        $config->getRuntimeConfig('load', array()),
      );
    }

    foreach ($load as $spec) {
      list($source, $libraries) = $spec;
      if ($is_trace) {
        $this->logTrace(
          pht('LOAD'),
          pht(
            'Loading libraries from "%s"...',
            $source));
      }

      if (!$libraries) {
        if ($is_trace) {
          $this->logTrace(pht('NONE'), pht('Nothing to load.'));
        }
        continue;
      }

      if (!is_array($libraries)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Libraries specified by "%s" are not formatted correctly. '.
            'Expected a list of paths. Check your configuration.',
            $source));
      }

      foreach ($libraries as $library) {
        $this->loadLibrary($source, $library, $working_copy, $is_trace);
      }
    }
  }

  private function loadLibrary(
    $source,
    $location,
    ArcanistWorkingCopyIdentity $working_copy,
    $is_trace) {

    // Try to resolve the library location. We look in several places, in
    // order:
    //
    //  1. Inside the working copy. This is for phutil libraries within the
    //     project. For instance "library/src" will resolve to
    //     "./library/src" if it exists.
    //  2. In the same directory as the working copy. This allows you to
    //     check out a library alongside a working copy and reference it.
    //     If we haven't resolved yet, "library/src" will try to resolve to
    //     "../library/src" if it exists.
    //  3. Using normal libphutil resolution rules. Generally, this means
    //     that it checks for libraries next to libphutil, then libraries
    //     in the PHP include_path.
    //
    // Note that absolute paths will just resolve absolutely through rule (1).

    $resolved = false;

    // Check inside the working copy. This also checks absolute paths, since
    // they'll resolve absolute and just ignore the project root.
    $resolved_location = Filesystem::resolvePath(
      $location,
      $working_copy->getProjectRoot());
    if (Filesystem::pathExists($resolved_location)) {
      $location = $resolved_location;
      $resolved = true;
    }

    // If we didn't find anything, check alongside the working copy.
    if (!$resolved) {
      $resolved_location = Filesystem::resolvePath(
        $location,
        dirname($working_copy->getProjectRoot()));
      if (Filesystem::pathExists($resolved_location)) {
        $location = $resolved_location;
        $resolved = true;
      }
    }

    if ($is_trace) {
      $this->logTrace(
        pht('LOAD'),
        pht('Loading phutil library from "%s"...', $location));
    }

    $error = null;
    try {
      phutil_load_library($location);
    } catch (PhutilBootloaderException $ex) {
      fwrite(
        STDERR,
        "%s",
        tsprintf(
          "**<bg:red> %s </bg>** %s\n",
          pht(
            'Failed to load phutil library at location "%s". This library '.
            'is specified by "%s". Check that the setting is correct and '.
            'the library is located in the right place.',
            $location,
            $source)));

      $prompt = pht('Continue without loading library?');
      if (!phutil_console_confirm($prompt)) {
        throw $ex;
      }
    } catch (PhutilLibraryConflictException $ex) {
      if ($ex->getLibrary() != 'arcanist') {
        throw $ex;
      }

      // NOTE: If you are running `arc` against itself, we ignore the library
      // conflict created by loading the local `arc` library (in the current
      // working directory) and continue without loading it.

      // This means we only execute code in the `arcanist/` directory which is
      // associated with the binary you are running, whereas we would normally
      // execute local code.

      // This can make `arc` development slightly confusing if your setup is
      // especially bizarre, but it allows `arc` to be used in automation
      // workflows more easily. For some context, see PHI13.

      $executing_directory = dirname(dirname(__FILE__));
      $working_directory = dirname($location);

      fwrite(
        STDERR,
        tsprintf(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('VERY META'),
          pht(
            'You are running one copy of Arcanist (at path "%s") against '.
            'another copy of Arcanist (at path "%s"). Code in the current '.
            'working directory will not be loaded or executed.',
            $executing_directory,
            $working_directory)));
    }
  }

  private function newToolset(array $argv) {
    $binary = basename($argv[0]);

    $toolsets = ArcanistToolset::newToolsetMap();
    if (!isset($toolsets[$binary])) {
      throw new PhutilArgumentUsageException(
        pht(
          'Arcanist toolset "%s" is unknown. The Arcanist binary should '.
          'be executed so that "argv[0]" identifies a supported toolset. '.
          'Rename the binary or install the library that provides the '.
          'desired toolset. Current available toolsets: %s.',
          $binary,
          implode(', ', array_keys($toolsets))));
    }

    return $toolsets[$binary];
  }

  private function newWorkflows(ArcanistToolset $toolset) {
    $workflows = id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistWorkflow')
      ->execute();

    foreach ($workflows as $key => $workflow) {
      if (!$workflow->supportsToolset($toolset)) {
        unset($workflows[$key]);
      }
    }

    $map = array();
    foreach ($workflows as $workflow) {
      $key = $workflow->getWorkflowName();
      if (isset($map[$key])) {
        throw new Exception(
          pht(
            'Two workflows ("%s" and "%s") both have the same name ("%s") '.
            'and both support the current toolset ("%s", "%s"). Each '.
            'workflow in a given toolset must have a unique name.',
            get_class($workflow),
            get_class($map[$key]),
            get_class($toolset),
            $toolset->getToolsetKey()));
      }
      $map[$key] = id(clone $workflow)
        ->setToolset($toolset);
    }

    return $map;
  }

  private function resolveAliases(
    array $workflows,
    array $argv,
    ArcanistConfigurationSourceList $config) {

    $command = head($argv);

    // If this is a match for a recognized workflow, just return the arguments
    // unmodified. You aren't allowed to alias over real workflows.
    if (isset($workflows[$command])) {
      return $argv;
    }

    $aliases = ArcanistAliasWorkflow::getAliases($config);
    list($new_command, $new_args) = ArcanistAliasWorkflow::resolveAliases(
      $command,
      $this,
      array_slice($argv, 1),
      $config);

    // You can't alias something to itself, so if the new command isn't new,
    // we're all done resolving aliases.
    if ($new_command === $command) {
      return $argv;
    }

    $full_alias = idx($aliases, $command, array());
    $full_alias = implode(' ', $full_alias);

    // Run shell command aliases.
    if (ArcanistAliasWorkflow::isShellCommandAlias($new_command)) {
      fwrite(
        STDERR,
        tsprintf(
          '**<bg:green> %s </bg>** arc %s -> $ %s',
          pht('ALIAS'),
          $command,
          $shell_cmd));

      $shell_cmd = substr($full_alias, 1);

      return phutil_passthru('%C %Ls', $shell_cmd, $args);
    }

    fwrite(
      STDERR,
      tsprintf(
        '**<bg:green> %s </bg>** arc %s -> arc %s',
        pht('ALIAS'),
        $command,
        $new_command));

    $new_argv = array_merge(array($new_command), $new_args);

    return $this->resolveAliases($workflows, $new_argv, $config);
  }

  private function logTrace($label, $message) {
    echo tsprintf(
      "**<bg:magenta> %s </bg>** %s\n",
      $label,
      $message);
  }

}
