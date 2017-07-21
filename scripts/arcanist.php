#!/usr/bin/env php
<?php

sanity_check_environment();

require_once dirname(__FILE__).'/__init_script__.php';

ini_set('memory_limit', -1);

$original_argv = $argv;
$base_args = new PhutilArgumentParser($argv);
$base_args->parseStandardArguments();
$base_args->parsePartial(
  array(
    array(
      'name'    => 'load-phutil-library',
      'param'   => 'path',
      'help'    => pht('Load a libphutil library.'),
      'repeat'  => true,
    ),
    array(
      'name'    => 'skip-arcconfig',
    ),
    array(
      'name'    => 'arcrc-file',
      'param'   => 'filename',
    ),
    array(
      'name'    => 'conduit-uri',
      'param'   => 'uri',
      'help'    => pht('Connect to Phabricator install specified by __uri__.'),
    ),
    array(
      'name' => 'conduit-token',
      'param' => 'token',
      'help' => pht('Use a specific authentication token.'),
    ),
    array(
      'name'    => 'conduit-version',
      'param'   => 'version',
      'help'    => pht(
        '(Developers) Mock client version in protocol handshake.'),
    ),
    array(
      'name'    => 'conduit-timeout',
      'param'   => 'timeout',
      'help'    => pht('Set Conduit timeout (in seconds).'),
    ),
    array(
      'name'   => 'config',
      'param'  => 'key=value',
      'repeat' => true,
      'help'   => pht(
        'Specify a runtime configuration value. This will take precedence '.
        'over static values, and only affect the current arcanist invocation.'),
    ),
));

$config_trace_mode = $base_args->getArg('trace');

$force_conduit = $base_args->getArg('conduit-uri');
$force_token = $base_args->getArg('conduit-token');
$force_conduit_version = $base_args->getArg('conduit-version');
$conduit_timeout = $base_args->getArg('conduit-timeout');
$skip_arcconfig = $base_args->getArg('skip-arcconfig');
$custom_arcrc = $base_args->getArg('arcrc-file');
$load = $base_args->getArg('load-phutil-library');
$help = $base_args->getArg('help');
$args = array_values($base_args->getUnconsumedArgumentVector());

$working_directory = getcwd();
$console = PhutilConsole::getConsole();
$config = null;
$workflow = null;

try {
  if ($config_trace_mode) {
    echo tsprintf(
      "**<bg:magenta> %s </bg>** %s\n",
      pht('ARGV'),
      csprintf('%Ls', $original_argv));

    $libraries = array(
      'phutil',
      'arcanist',
    );

    foreach ($libraries as $library_name) {
      echo tsprintf(
        "**<bg:magenta> %s </bg>** %s\n",
        pht('LOAD'),
        pht(
          'Loaded "%s" from "%s".',
          $library_name,
          phutil_get_library_root($library_name)));
    }
  }

  if (!$args) {
    if ($help) {
      $args = array('help');
    } else {
      throw new ArcanistUsageException(
        pht('No command provided. Try `%s`.', 'arc help'));
    }
  } else if ($help) {
    array_unshift($args, 'help');
  }

  $configuration_manager = new ArcanistConfigurationManager();
  if ($custom_arcrc) {
    $configuration_manager->setUserConfigurationFileLocation($custom_arcrc);
  }

  $global_config = $configuration_manager->readUserArcConfig();
  $system_config = $configuration_manager->readSystemArcConfig();
  $runtime_config = $configuration_manager->applyRuntimeArcConfig($base_args);

  if ($skip_arcconfig) {
    $working_copy = ArcanistWorkingCopyIdentity::newDummyWorkingCopy();
  } else {
    $working_copy =
      ArcanistWorkingCopyIdentity::newFromPath($working_directory);
  }
  $configuration_manager->setWorkingCopyIdentity($working_copy);

  // Load additional libraries, which can provide new classes like configuration
  // overrides, linters and lint engines, unit test engines, etc.

  // If the user specified "--load-phutil-library" one or more times from
  // the command line, we load those libraries **instead** of whatever else
  // is configured. This is basically a debugging feature to let you force
  // specific libraries to load regardless of the state of the world.
  if ($load) {
    $console->writeLog(
      "%s\n",
      pht(
        'Using `%s` flag, configuration will be ignored and configured '.
        'libraries will not be loaded.',
        '--load-phutil-library'));
    // Load the flag libraries. These must load, since the user specified them
    // explicitly.
    arcanist_load_libraries(
      $load,
      $must_load = true,
      $lib_source = pht('a "%s" flag', '--load-phutil-library'),
      $working_copy);
  } else {
    // Load libraries in system 'load' config. In contrast to global config, we
    // fail hard here because this file is edited manually, so if 'arc' breaks
    // that doesn't make it any more difficult to correct.
    arcanist_load_libraries(
      idx($system_config, 'load', array()),
      $must_load = true,
      $lib_source = pht('the "%s" setting in system config', 'load'),
      $working_copy);

    // Load libraries in global 'load' config, as per "arc set-config load". We
    // need to fail softly if these break because errors would prevent the user
    // from running "arc set-config" to correct them.
    arcanist_load_libraries(
      idx($global_config, 'load', array()),
      $must_load = false,
      $lib_source = pht('the "%s" setting in global config', 'load'),
      $working_copy);

    // Load libraries in ".arcconfig". Libraries here must load.
    arcanist_load_libraries(
      $working_copy->getProjectConfig('load'),
      $must_load = true,
      $lib_source = pht('the "%s" setting in "%s"', 'load', '.arcconfig'),
      $working_copy);

    // Load libraries in ".arcconfig". Libraries here must load.
    arcanist_load_libraries(
      idx($runtime_config, 'load', array()),
      $must_load = true,
      $lib_source = pht('the %s argument', '--config "load=[...]"'),
      $working_copy);
  }

  $user_config = $configuration_manager->readUserConfigurationFile();

  $config_class = $working_copy->getProjectConfig('arcanist_configuration');
  if ($config_class) {
    $config = new $config_class();
  } else {
    $config = new ArcanistConfiguration();
  }

  $command = strtolower($args[0]);
  $args = array_slice($args, 1);
  $workflow = $config->selectWorkflow(
    $command,
    $args,
    $configuration_manager,
    $console);
  $workflow->setConfigurationManager($configuration_manager);
  $workflow->setArcanistConfiguration($config);
  $workflow->setCommand($command);
  $workflow->setWorkingDirectory($working_directory);
  $workflow->parseArguments($args);

  // Write the command into the environment so that scripts (for example, local
  // Git commit hooks) can detect that they're being run via `arc` and change
  // their behaviors.
  putenv('ARCANIST='.$command);

  if ($force_conduit_version) {
    $workflow->forceConduitVersion($force_conduit_version);
  }
  if ($conduit_timeout) {
    $workflow->setConduitTimeout($conduit_timeout);
  }

  $need_working_copy = $workflow->requiresWorkingCopy();

  $supported_vcs_types = $workflow->getSupportedRevisionControlSystems();
  $vcs_type = $working_copy->getVCSType();
  if ($vcs_type || $need_working_copy) {
    if (!in_array($vcs_type, $supported_vcs_types)) {
      throw new ArcanistUsageException(
        pht(
          '`%s %s` is only supported under %s.',
          'arc',
          $workflow->getWorkflowName(),
          implode(', ', $supported_vcs_types)));
    }
  }

  $need_conduit         = $workflow->requiresConduit();
  $need_auth            = $workflow->requiresAuthentication();
  $need_repository_api  = $workflow->requiresRepositoryAPI();

  $want_repository_api = $workflow->desiresRepositoryAPI();
  $want_working_copy = $workflow->desiresWorkingCopy() ||
                       $want_repository_api;

  $need_conduit       = $need_conduit ||
                        $need_auth;
  $need_working_copy  = $need_working_copy ||
                        $need_repository_api;

  if ($need_working_copy || $want_working_copy) {
    if ($need_working_copy && !$working_copy->getVCSType()) {
      throw new ArcanistUsageException(
        pht(
          'This command must be run in a Git, Mercurial or Subversion '.
          'working copy.'));
    }
    $configuration_manager->setWorkingCopyIdentity($working_copy);
  }

  if ($force_conduit) {
    $conduit_uri = $force_conduit;
  } else {
    $conduit_uri = $configuration_manager->getConfigFromAnySource(
      'phabricator.uri');
    if ($conduit_uri === null) {
      $conduit_uri = $configuration_manager->getConfigFromAnySource('default');
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
    $conduit_uri = (string)$conduit_uri;
  }
  $workflow->setConduitURI($conduit_uri);

  // Apply global CA bundle from configs.
  $ca_bundle = $configuration_manager->getConfigFromAnySource('https.cabundle');
  if ($ca_bundle) {
    $ca_bundle = Filesystem::resolvePath(
      $ca_bundle, $working_copy->getProjectRoot());
    HTTPSFuture::setGlobalCABundleFromPath($ca_bundle);
  }

  $blind_key = 'https.blindly-trust-domains';
  $blind_trust = $configuration_manager->getConfigFromAnySource($blind_key);
  if ($blind_trust) {
    $trust_extension = PhutilHTTPEngineExtension::requireExtension(
      ArcanistBlindlyTrustHTTPEngineExtension::EXTENSIONKEY);
    $trust_extension->setDomains($blind_trust);
  }

  if ($need_conduit) {
    if (!$conduit_uri) {

      $message = phutil_console_format(
        "%s\n\n  - %s\n  - %s\n  - %s\n",
        pht(
          'This command requires arc to connect to a Phabricator install, '.
          'but no Phabricator installation is configured. To configure a '.
          'Phabricator URI:'),
        pht(
          'set a default location with `%s`; or',
          'arc set-config default <uri>'),
        pht(
          'specify `%s` explicitly; or',
          '--conduit-uri=uri'),
        pht(
          "run `%s` in a working copy with an '%s'.",
          'arc',
          '.arcconfig'));

      $message = phutil_console_wrap($message);
      throw new ArcanistUsageException($message);
    }
    $workflow->establishConduit();
  }

  $hosts_config = idx($user_config, 'hosts', array());
  $host_config = idx($hosts_config, $conduit_uri, array());
  $user_name = idx($host_config, 'user');
  $certificate = idx($host_config, 'cert');
  $conduit_token = idx($host_config, 'token');
  if ($force_token) {
    $conduit_token = $force_token;
  }

  $description = implode(' ', $original_argv);
  $credentials = array(
    'user' => $user_name,
    'certificate' => $certificate,
    'description' => $description,
    'token' => $conduit_token,
  );
  $workflow->setConduitCredentials($credentials);

  if ($need_auth) {
    if ((!$user_name || !$certificate) && (!$conduit_token)) {
      $arc = 'arc';
      if ($force_conduit) {
        $arc .= csprintf(' --conduit-uri=%s', $conduit_uri);
      }

      $conduit_domain = id(new PhutilURI($conduit_uri))->getDomain();

      throw new ArcanistUsageException(
        phutil_console_format(
          "%s\n\n%s\n\n%s **%s:**\n\n      $ **{$arc} install-certificate**\n",
          pht('YOU NEED TO AUTHENTICATE TO CONTINUE'),
          pht(
            'You are trying to connect to a server (%s) that you '.
            'do not have any credentials stored for.',
            $conduit_domain),
          pht('To retrieve and store credentials for this server,'),
          pht('run this command')));
    }
    $workflow->authenticateConduit();
  }

  if ($need_repository_api ||
      ($want_repository_api && $working_copy->getVCSType())) {
    $repository_api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);
    $workflow->setRepositoryAPI($repository_api);
  }

  $listeners = $configuration_manager->getConfigFromAnySource(
    'events.listeners');
  if ($listeners) {
    foreach ($listeners as $listener) {
      $console->writeLog(
        "%s\n",
        pht("Registering event listener '%s'.", $listener));
      try {
        id(new $listener())->register();
      } catch (PhutilMissingSymbolException $ex) {
        // Continue anyway, since you may otherwise be unable to run commands
        // like `arc set-config events.listeners` in order to repair the damage
        // you've caused. We're writing out the entire exception here because
        // it might not have been triggered by the listener itself (for example,
        // the listener might use a bad class in its register() method).
        $console->writeErr(
          "%s\n",
          pht(
            "ERROR: Failed to load event listener '%s': %s",
            $listener,
            $ex->getMessage()));
      }
    }
  }

  $config->willRunWorkflow($command, $workflow);
  $workflow->willRunWorkflow();
  try {
    $err = $workflow->run();
    $config->didRunWorkflow($command, $workflow, $err);
  } catch (Exception $e) {
    $workflow->finalize();
    throw $e;
  }
  $workflow->finalize();
  exit((int)$err);

} catch (ArcanistNoEffectException $ex) {
  echo $ex->getMessage()."\n";

} catch (Exception $ex) {
  $is_usage = ($ex instanceof ArcanistUsageException);
  if ($is_usage) {
    fwrite(STDERR, phutil_console_format(
      "**%s** %s\n",
      pht('Usage Exception:'),
      $ex->getMessage()));
  }

  if ($config) {
    $config->didAbortWorkflow($command, $workflow, $ex);
  }

  if ($config_trace_mode) {
    fwrite(STDERR, "\n");
    throw $ex;
  }

  if (!$is_usage) {
    fwrite(STDERR, phutil_console_format(
      "<bg:red>** %s **</bg>\n", pht('Exception')));

    while ($ex) {
      fwrite(STDERR, $ex->getMessage()."\n");

      if ($ex instanceof PhutilProxyException) {
        $ex = $ex->getPreviousException();
      } else {
        $ex = null;
      }
    }

    fwrite(STDERR, phutil_console_format(
      "(%s)\n",
      pht('Run with `%s` for a full exception trace.', '--trace')));
  }

  exit(1);
}


/**
 * Perform some sanity checks against the possible diversity of PHP builds in
 * the wild, like very old versions and builds that were compiled with flags
 * that exclude core functionality.
 */
function sanity_check_environment() {
  // NOTE: We don't have phutil_is_windows() yet here.
  $is_windows = (DIRECTORY_SEPARATOR != '/');

  // We use stream_socket_pair() which is not available on Windows earlier.
  $min_version = ($is_windows ? '5.3.0' : '5.2.3');
  $cur_version = phpversion();
  if (version_compare($cur_version, $min_version, '<')) {
    die_with_bad_php(
      "You are running PHP version '{$cur_version}', which is older than ".
      "the minimum version, '{$min_version}'. Update to at least ".
      "'{$min_version}'.");
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

    $generic = true;
    list($what, $which) = $resolution;

    if ($what == 'flag' && strpos($config, $which) !== false) {
      $show_config = true;
      $generic = false;
      $problems[] =
        "This build of PHP was compiled with the configure flag '{$which}', ".
        "which means it does not have the function '{$fname}()'. This ".
        "function is required for arc to run. Rebuild PHP without this flag. ".
        "You may also be able to build or install the relevant extension ".
        "separately.";
    }

    if ($what == 'builtin-dll') {
      $generic = false;
      $problems[] =
        "Your install of PHP does not have the '{$which}' extension enabled. ".
        "Edit your php.ini file and uncomment the line which reads ".
        "'extension={$which}'.";
    }

    if ($what == 'text') {
      $generic = false;
      $problems[] = $which;
    }

    if ($generic) {
      $problems[] =
        "This build of PHP is missing the required function '{$fname}()'. ".
        "Rebuild PHP or install the extension which provides '{$fname}()'.";
    }
  }

  if ($problems) {
    if ($show_config) {
      $problems[] = "PHP was built with this configure command:\n\n{$config}";
    }
    die_with_bad_php(implode("\n\n", $problems));
  }
}

function die_with_bad_php($message) {
  // NOTE: We're bailing because PHP is broken. We can't call any library
  // functions because they won't be loaded yet.

  echo "\n";
  echo 'PHP CONFIGURATION ERRORS';
  echo "\n\n";
  echo $message;
  echo "\n\n";
  exit(1);
}

function arcanist_load_libraries(
  $load,
  $must_load,
  $lib_source,
  ArcanistWorkingCopyIdentity $working_copy) {

  if (!$load) {
    return;
  }

  if (!is_array($load)) {
    $error = pht(
      'Libraries specified by %s are invalid; expected a list. '.
      'Check your configuration.',
      $lib_source);
    $console = PhutilConsole::getConsole();
    $console->writeErr("%s: %s\n", pht('WARNING'), $error);
    return;
  }

  foreach ($load as $location) {

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

    $console = PhutilConsole::getConsole();
    $console->writeLog(
      "%s\n",
      pht("Loading phutil library from '%s'...", $location));

    $error = null;
    try {
      phutil_load_library($location);
    } catch (PhutilBootloaderException $ex) {
      $error = pht(
        "Failed to load phutil library at location '%s'. This library ".
        "is specified by %s. Check that the setting is correct and the ".
        "library is located in the right place.",
        $location,
        $lib_source);
      if ($must_load) {
        throw new ArcanistUsageException($error);
      } else {
        fwrite(STDERR, phutil_console_wrap(
          phutil_console_format("%s: %s\n",
                                pht('WARNING'),
                                $error)));
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
}
