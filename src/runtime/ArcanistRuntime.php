<?php

final class ArcanistRuntime {

  private $workflows;
  private $logEngine;
  private $lastInterruptTime;

  private $stack = array();

  private $viewer;
  private $toolset;
  private $hardpointEngine;
  private $symbolEngine;
  private $conduitEngine;
  private $workingCopy;

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
    } catch (ArcanistConduitAuthenticationException $ex) {
      $log->writeError($ex->getTitle(), $ex->getBody());
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

    // If we can test whether STDIN is a TTY, and it isn't, require that "--"
    // appear in the argument list. This is intended to make it very hard to
    // write unsafe scripts on top of Arcanist.

    if (phutil_is_noninteractive()) {
      $args->setRequireArgumentTerminator(true);
    }

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

    $this->loadLibraries($config_engine, $config, $args);

    // Now that we've loaded libraries, we can validate configuration.
    // Do this before continuing since configuration can impact other
    // behaviors immediately and we want to catch any issues right away.
    $config->setConfigOptions($config_engine->newConfigOptionsMap());
    $config->validateConfiguration($this);

    $toolset = $this->newToolset($argv);
    $this->setToolset($toolset);

    $args->parsePartial($toolset->getToolsetArguments());

    $workflows = $this->newWorkflows($toolset);
    $this->workflows = $workflows;

    $conduit_engine = $this->newConduitEngine($config, $args);
    $this->conduitEngine = $conduit_engine;

    $phutil_workflows = array();
    foreach ($workflows as $key => $workflow) {
      $workflow
        ->setRuntime($this)
        ->setConfigurationEngine($config_engine)
        ->setConfigurationSourceList($config)
        ->setConduitEngine($conduit_engine);

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

    $alias_effects = id(new ArcanistAliasEngine())
      ->setRuntime($this)
      ->setToolset($toolset)
      ->setWorkflows($workflows)
      ->setConfigurationSourceList($config)
      ->resolveAliases($unconsumed_argv);

    foreach ($alias_effects as $alias_effect) {
      if ($alias_effect->getType() === ArcanistAliasEffect::EFFECT_SHELL) {
        return $this->executeShellAlias($alias_effect);
      }
    }

    $result_argv = $this->applyAliasEffects($alias_effects, $unconsumed_argv);

    $args->setUnconsumedArgumentVector($result_argv);

    // TOOLSETS: Some day, stop falling through to the old "arc" runtime.

    $help_workflows = $this->getHelpWorkflows($phutil_workflows);
    $args->setHelpWorkflows($help_workflows);

    try {
      return $args->parseWorkflowsFull($phutil_workflows);
    } catch (ArcanistMissingArgumentTerminatorException $terminator_exception) {
      $log->writeHint(
        pht('USAGE'),
        pht(
          '"%s" is being run noninteractively, but the argument list is '.
          'missing "--" to indicate end of flags.',
          $toolset->getToolsetKey()));

      $log->writeHint(
        pht('USAGE'),
        pht(
          'When running noninteractively, you MUST provide "--" to all '.
          'commands (even if they take no arguments).'));

      $log->writeHint(
        pht('USAGE'),
        tsprintf(
          '%s <__%s__>',
          pht('Learn More:'),
          'https://phurl.io/u/noninteractive'));

      throw new PhutilArgumentUsageException(
        pht('Missing required "--" in argument list.'));
    } catch (PhutilArgumentUsageException $usage_exception) {

      // TODO: This is very, very hacky; we're trying to let errors like
      // "you passed the wrong arguments" through but fall back to classic
      // mode if the workflow itself doesn't exist.
      if (!preg_match('/invalid command/i', $usage_exception->getMessage())) {
        throw $usage_exception;
      }

    }

    $arcanist_root = phutil_get_library_root('arcanist');
    $arcanist_root = dirname($arcanist_root);
    $bin = $arcanist_root.'/scripts/arcanist.php';

    $err = phutil_passthru(
      'php -f %R -- %Ls',
      $bin,
      array_slice($argv, 1));

    return $err;
  }


  /**
   * Perform some sanity checks against the possible diversity of PHP builds in
   * the wild, like very old versions and builds that were compiled with flags
   * that exclude core functionality.
   */
  private function checkEnvironment() {
    // NOTE: We don't have phutil_is_windows() yet here.
    $is_windows = (DIRECTORY_SEPARATOR != '/');

    // NOTE: There's a hard PHP version check earlier, in "init-script.php".

    if ($is_windows) {
      $need_functions = array(
        'curl_init' => array('builtin-dll', 'php_curl.dll'),
      );
    } else {
      $need_functions = array(
        'curl_init' => array(
          'text',
          "You need to install the cURL PHP extension, maybe with ".
          "'apt-get install php5-curl' or 'yum install php53-curl' or ".
          "something similar.",
        ),
        'json_decode' => array('flag', '--without-json'),
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

    $engine->setWorkingCopy($working_copy);

    $this->workingCopy = $working_copy;

    $working_copy
      ->getRepositoryAPI()
      ->setRuntime($this);

    return $engine;
  }

  private function loadLibraries(
    ArcanistConfigurationEngine $engine,
    ArcanistConfigurationSourceList $config,
    PhutilArgumentParser $args) {

    $sources = array();

    $cli_libraries = $args->getArg('library');
    if ($cli_libraries) {
      $sources = array();
      foreach ($cli_libraries as $cli_library) {
        $sources[] = array(
          'type' => 'flag',
          'library-source' => $cli_library,
        );
      }
    } else {
      $items = $config->getStorageValueList('load');
      foreach ($items as $item) {
        foreach ($item->getValue() as $library_path) {
          $sources[] = array(
            'type' => 'config',
            'config-source' => $item->getConfigurationSource(),
            'library-source' => $library_path,
          );
        }
      }
    }

    foreach ($sources as $spec) {
      $library_source = $spec['library-source'];

      switch ($spec['type']) {
        case 'flag':
          $description = pht('runtime --library flag');
          break;
        case 'config':
          $config_source = $spec['config-source'];
          $description = pht(
            'Configuration (%s)',
            $config_source->getSourceDisplayName());
          break;
      }

      $this->loadLibrary($engine, $library_source, $description);
    }
  }

  private function loadLibrary(
    ArcanistConfigurationEngine $engine,
    $location,
    $description) {

    // TODO: This is a legacy system that should be replaced with package
    // management.

    $log = $this->getLogEngine();
    $working_copy = $engine->getWorkingCopy();
    if ($working_copy) {
      $working_copy_root = $working_copy->getPath();
      $working_directory = $working_copy->getWorkingDirectory();
    } else {
      $working_copy_root = null;
      $working_directory = getcwd();
    }

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
    if ($working_copy_root !== null) {
      $resolved_location = Filesystem::resolvePath(
        $location,
        $working_copy_root);
      if (Filesystem::pathExists($resolved_location)) {
        $location = $resolved_location;
        $resolved = true;
      }

      // If we didn't find anything, check alongside the working copy.
      if (!$resolved) {
        $resolved_location = Filesystem::resolvePath(
          $location,
          dirname($working_copy_root));
        if (Filesystem::pathExists($resolved_location)) {
          $location = $resolved_location;
          $resolved = true;
        }
      }
    }

    // Look beside "arcanist/". This is rule (3) above.

    if (!$resolved) {
      $arcanist_root = phutil_get_library_root('arcanist');
      $arcanist_root = dirname(dirname($arcanist_root));
      $resolved_location = Filesystem::resolvePath(
        $location,
        $arcanist_root);
      if (Filesystem::pathExists($resolved_location)) {
        $location = $resolved_location;
        $resolved = true;
      }
    }

    $log->writeTrace(
      pht('LOAD'),
      pht('Loading library from "%s"...', $location));

    $error = null;
    try {
      phutil_load_library($location);
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

      $log->writeWarn(
        pht('VERY META'),
        pht(
          'You are running one copy of Arcanist (at path "%s") against '.
          'another copy of Arcanist (at path "%s"). Code in the current '.
          'working directory will not be loaded or executed.',
          $executing_directory,
          $working_directory));
    } catch (PhutilBootloaderException $ex) {
      $log->writeError(
        pht('LIBRARY ERROR'),
        pht(
          'Failed to load library at location "%s". This library '.
          'is specified by "%s". Check that the library is up to date.',
          $location,
          $description));

      $prompt = pht('Continue without loading library?');
      if (!phutil_console_confirm($prompt)) {
        throw $ex;
      }
    } catch (Exception $ex) {
      $log->writeError(
        pht('LOAD ERROR'),
        pht(
          'Failed to load library at location "%s". This library is '.
          'specified by "%s". Check that the setting is correct and the '.
          'library is located in the right place.',
          $location,
          $description));

      $prompt = pht('Continue without loading library?');
      if (!phutil_console_confirm($prompt)) {
        throw $ex;
      }
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
      ->setContinueOnFailure(true)
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
            $key,
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
        $log->writeHint(pht('ALIAS'), $message);
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

  public function getCurrentWorkflow() {
    return last($this->stack);
  }

  private function newConduitEngine(
    ArcanistConfigurationSourceList $config,
    PhutilArgumentParser $args) {

    try {
      $force_uri = $args->getArg('conduit-uri');
    } catch (PhutilArgumentSpecificationException $ex) {
      $force_uri = null;
    }

    try {
      $force_token = $args->getArg('conduit-token');
    } catch (PhutilArgumentSpecificationException $ex) {
      $force_token = null;
    }

    if ($force_uri !== null) {
      $conduit_uri = $force_uri;
    } else {
      $conduit_uri = $config->getConfig('phabricator.uri');
      if ($conduit_uri === null) {
        // For now, read this older config from raw storage. There is currently
        // no definition of this option in the "toolsets" config list, and it
        // would be nice to get rid of it.
        $default_list = $config->getStorageValueList('default');
        if ($default_list) {
          $conduit_uri = last($default_list)->getValue();
        }
      }
    }

    if ($conduit_uri) {
      // Set the URI path to '/api/'. TODO: Originally, I contemplated letting
      // you deploy Phabricator somewhere other than the domain root, but ended
      // up never pursuing that. We should get rid of all "/api/" silliness
      // in things users are expected to configure. This is already happening
      // to some degree, e.g. "arc install-certificate" does it for you.
      $conduit_uri = new PhutilURI($conduit_uri);
      $conduit_uri->setPath('/api/');
      $conduit_uri = phutil_string_cast($conduit_uri);
    }

    $engine = new ArcanistConduitEngine();

    if ($conduit_uri !== null) {
      $engine->setConduitURI($conduit_uri);
    }

    // TODO: This isn't using "getConfig()" because we aren't defining a
    // real config entry for the moment.

    if ($force_token !== null) {
      $conduit_token = $force_token;
    } else {
      $hosts = array();

      $hosts_list = $config->getStorageValueList('hosts');
      foreach ($hosts_list as $hosts_config) {
        $hosts += $hosts_config->getValue();
      }

      $host_config = idx($hosts, $conduit_uri, array());
      $conduit_token = idx($host_config, 'token');
    }

    if ($conduit_token !== null) {
      $engine->setConduitToken($conduit_token);
    }

    return $engine;
  }

  private function executeShellAlias(ArcanistAliasEffect $effect) {
    $log = $this->getLogEngine();

    $message = $effect->getMessage();
    if ($message !== null) {
      $log->writeHint(pht('SHELL ALIAS'), $message);
    }

    return phutil_passthru('%Ls', $effect->getArguments());
  }

  public function getSymbolEngine() {
    if ($this->symbolEngine === null) {
      $this->symbolEngine = $this->newSymbolEngine();
    }
    return $this->symbolEngine;
  }

  private function newSymbolEngine() {
    return id(new ArcanistSymbolEngine())
      ->setWorkflow($this);
  }

  public function getHardpointEngine() {
    if ($this->hardpointEngine === null) {
      $this->hardpointEngine = $this->newHardpointEngine();
    }
    return $this->hardpointEngine;
  }

  private function newHardpointEngine() {
    $engine = new ArcanistHardpointEngine();

    $queries = ArcanistRuntimeHardpointQuery::getAllQueries();

    foreach ($queries as $key => $query) {
      $queries[$key] = id(clone $query)
        ->setRuntime($this);
    }

    $engine->setQueries($queries);

    return $engine;
  }

  public function getViewer() {
    if (!$this->viewer) {
      $viewer = $this->getSymbolEngine()
        ->loadUserForSymbol('viewer()');

      // TODO: Deal with anonymous stuff.
      if (!$viewer) {
        throw new Exception(pht('No viewer!'));
      }

      $this->viewer = $viewer;
    }

    return $this->viewer;
  }

  public function loadHardpoints($objects, $requests) {
    if (!is_array($objects)) {
      $objects = array($objects);
    }

    if (!is_array($requests)) {
      $requests = array($requests);
    }

    $engine = $this->getHardpointEngine();

    $requests = $engine->requestHardpoints(
      $objects,
      $requests);

    // TODO: Wait for only the required requests.
    $engine->waitForRequests(array());
  }

  public function getWorkingCopy() {
    return $this->workingCopy;
  }

  public function getConduitEngine() {
    return $this->conduitEngine;
  }

  public function setToolset($toolset) {
    $this->toolset = $toolset;
    return $this;
  }

  public function getToolset() {
    return $this->toolset;
  }

  private function getHelpWorkflows(array $workflows) {
    if ($this->getToolset()->getToolsetKey() === 'arc') {
      $legacy = array();

      $legacy[] = new ArcanistCloseRevisionWorkflow();
      $legacy[] = new ArcanistCommitWorkflow();
      $legacy[] = new ArcanistCoverWorkflow();
      $legacy[] = new ArcanistDiffWorkflow();
      $legacy[] = new ArcanistExportWorkflow();
      $legacy[] = new ArcanistGetConfigWorkflow();
      $legacy[] = new ArcanistSetConfigWorkflow();
      $legacy[] = new ArcanistInstallCertificateWorkflow();
      $legacy[] = new ArcanistLintersWorkflow();
      $legacy[] = new ArcanistLintWorkflow();
      $legacy[] = new ArcanistListWorkflow();
      $legacy[] = new ArcanistPatchWorkflow();
      $legacy[] = new ArcanistPasteWorkflow();
      $legacy[] = new ArcanistTasksWorkflow();
      $legacy[] = new ArcanistTodoWorkflow();
      $legacy[] = new ArcanistUnitWorkflow();
      $legacy[] = new ArcanistWhichWorkflow();

      foreach ($legacy as $workflow) {
        // If this workflow has been updated but not removed from the list
        // above yet, just skip it.
        if ($workflow instanceof ArcanistArcWorkflow) {
          continue;
        }

        $workflows[] = $workflow->newLegacyPhutilWorkflow();
      }
    }

    return $workflows;
  }

}
