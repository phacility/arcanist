<?php

final class ArcanistRuntime {

  private $workflows;
  private $logEngine;
  private $lastInterruptTime;

  private $stack = array();

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

    $log = new ArcanistLogEngine();
    $this->logEngine = $log;

    try {
      return $this->executeCore($argv);
    } catch (ArcanistConduitException $ex) {
      $log->writeError(pht('CONDUIT'), $ex->getMessage());
    } catch (PhutilArgumentUsageException $ex) {
      $log->writeError(pht('USAGE EXCEPTION'), $ex->getMessage());
    } catch (ArcanistUserAbortException $ex) {
      $log->writeError(pht('---'), $ex->getMessage());
    }

    return 1;
  }

  private function executeCore(array $argv) {
    $log = $this->getLogEngine();

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
    $log->setShowTraceMessages($is_trace);

    $log->writeTrace(pht('ARGV'), csprintf('%Ls', $argv));

    // We're installing the signal handler after parsing "--trace" so that it
    // can emit debugging messages. This means there's a very small window at
    // startup where signals have no special handling, but we couldn't really
    // route them or do anything interesting with them anyway.
    $this->installSignalHandler();

    $args->parsePartial($config_args, true);

    $config_engine = $this->loadConfiguration($args);
    $config = $config_engine->newConfigurationSourceList();

    $this->loadLibraries($args, $config);

    // Now that we've loaded libraries, we can validate configuration.
    // Do this before continuing since configuration can impact other
    // behaviors immediately and we want to catch any issues right away.
    $config->setConfigOptions($config_engine->newConfigOptionsMap());
    $config->validateConfiguration($this);

    $toolset = $this->newToolset($argv);

    $args->parsePartial($toolset->getToolsetArguments());

    $workflows = $this->newWorkflows($toolset);
    $this->workflows = $workflows;

    $phutil_workflows = array();
    foreach ($workflows as $key => $workflow) {
      $phutil_workflows[$key] = $workflow->newPhutilWorkflow();

      $workflow
        ->setRuntime($this)
        ->setConfigurationEngine($config_engine)
        ->setConfigurationSourceList($config);
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

    $alias_effects = id(new ArcanistAliasEngine())
      ->setRuntime($this)
      ->setToolset($toolset)
      ->setWorkflows($workflows)
      ->setConfigurationSourceList($config)
      ->resolveAliases($unconsumed_argv);

    $result_argv = $this->applyAliasEffects($alias_effects, $unconsumed_argv);

    $args->setUnconsumedArgumentVector($result_argv);

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

    return $engine;
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

  public function getWorkflows() {
    return $this->workflows;
  }

  public function getLogEngine() {
    return $this->logEngine;
  }

  private function applyAliasEffects(array $effects, array $argv) {
    assert_instances_of($effects, 'ArcanistAliasEffect');

    $log = $this->getLogEngine();

    $command = null;
    $arguments = null;
    foreach ($effects as $effect) {
      $message = $effect->getMessage();

      if ($message !== null) {
        $log->writeInfo(pht('ALIAS'), $message);
      }

      if ($effect->getCommand()) {
        $command = $effect->getCommand();
        $arguments = $effect->getArguments();
      }
    }

    if ($command !== null) {
      $argv = array_merge(array($command), $arguments);
    }

    return $argv;
  }

  private function installSignalHandler() {
    $log = $this->getLogEngine();

    if (!function_exists('pcntl_signal')) {
      $log->writeTrace(
        pht('PCNTL'),
        pht(
          'Unable to install signal handler, pcntl_signal() unavailable. '.
          'Continuing without signal handling.'));
      return;
    }

    // NOTE: SIGHUP, SIGTERM and SIGWINCH are handled by "PhutilSignalRouter".
    // This logic is largely similar to the logic there, but more specific to
    // Arcanist workflows.

    pcntl_signal(SIGINT, array($this, 'routeSignal'));
  }

  public function routeSignal($signo) {
    switch ($signo) {
      case SIGINT:
        $this->routeInterruptSignal($signo);
        break;
    }
  }

  private function routeInterruptSignal($signo) {
    $log = $this->getLogEngine();

    $last_interrupt = $this->lastInterruptTime;
    $now = microtime(true);
    $this->lastInterruptTime = $now;

    $should_exit = false;

    // If we received another SIGINT recently, always exit. This implements
    // "press ^C twice in quick succession to exit" regardless of what the
    // workflow may decide to do.
    $interval = 2;
    if ($last_interrupt !== null) {
      if ($now - $last_interrupt < $interval) {
        $should_exit = true;
      }
    }

    $handler = null;
    if (!$should_exit) {

      // Look for an interrupt handler in the current workflow stack.

      $stack = $this->getWorkflowStack();
      foreach ($stack as $workflow) {
        if ($workflow->canHandleSignal($signo)) {
          $handler = $workflow;
          break;
        }
      }

      // If no workflow in the current execution stack can handle an interrupt
      // signal, just exit on the first interrupt.

      if (!$handler) {
        $should_exit = true;
      }
    }

    // It's common for users to ^C on prompts. Write a newline before writing
    // a response to the interrupt so the behavior is a little cleaner. This
    // also avoids lines that read "^C [ INTERRUPT ] ...".
    $log->writeNewline();

    if ($should_exit) {
      $log->writeHint(
        pht('INTERRUPT'),
        pht('Interrupted by SIGINT (^C).'));
      exit(128 + $signo);
    }

    $log->writeHint(
      pht('INTERRUPT'),
      pht('Press ^C again to exit.'));

    $handler->handleSignal($signo);
  }

  public function pushWorkflow(ArcanistWorkflow $workflow) {
    $this->stack[] = $workflow;
    return $this;
  }

  public function popWorkflow() {
    if (!$this->stack) {
      throw new Exception(pht('Trying to pop an empty workflow stack!'));
    }

    return array_pop($this->stack);
  }

  public function getWorkflowStack() {
    return $this->stack;
  }


}
