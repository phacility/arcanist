#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

sanity_check_environment();

require_once dirname(__FILE__).'/__init_script__.php';

ini_set('memory_limit', -1);

$original_argv = $argv;
$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parsePartial(
  array(
    array(
      'name'    => 'load-phutil-library',
      'param'   => 'path',
      'help'    => 'Load a libphutil library.',
      'repeat'  => true,
    ),
    array(
      'name'    => 'conduit-uri',
      'param'   => 'uri',
      'help'    => 'Connect to Phabricator install specified by __uri__.',
    ),
    array(
      'name'    => 'conduit-version',
      'param'   => 'version',
      'help'    => '(Developers) Mock client version in protocol handshake.',
    ),
    array(
      'name'    => 'conduit-timeout',
      'param'   => 'timeout',
      'help'    => 'Set Conduit timeout (in seconds).',
    ),
  ));

$config_trace_mode = $args->getArg('trace');

$force_conduit = $args->getArg('conduit-uri');
$force_conduit_version = $args->getArg('conduit-version');
$conduit_timeout = $args->getArg('conduit-timeout');
$load = $args->getArg('load-phutil-library');

$argv = $args->getUnconsumedArgumentVector();
$args = array_values($argv);

$working_directory = getcwd();
$console = PhutilConsole::getConsole();

try {

  $console->writeLog(
    "libphutil loaded from '%s'.\n",
    phutil_get_library_root('phutil'));
  $console->writeLog(
    "arcanist loaded from '%s'.\n",
    phutil_get_library_root('arcanist'));

  if (!$args) {
    throw new ArcanistUsageException("No command provided. Try 'arc help'.");
  }

  $global_config = ArcanistBaseWorkflow::readGlobalArcConfig();
  $system_config = ArcanistBaseWorkflow::readSystemArcConfig();
  $working_copy = ArcanistWorkingCopyIdentity::newFromPath($working_directory);

  // Load additional libraries, which can provide new classes like configuration
  // overrides, linters and lint engines, unit test engines, etc.

  // If the user specified "--load-phutil-library" one or more times from
  // the command line, we load those libraries **instead** of whatever else
  // is configured. This is basically a debugging feature to let you force
  // specific libraries to load regardless of the state of the world.
  if ($load) {
    // Load the flag libraries. These must load, since the user specified them
    // explicitly.
    arcanist_load_libraries(
      $load,
      $must_load = true,
      $lib_source = 'a "--load-phutil-library" flag',
      $working_copy);
  } else {
    // Load libraries in system 'load' config. In contrast to global config, we
    // fail hard here because this file is edited manually, so if 'arc' breaks
    // that doesn't make it any more difficult to correct.
    arcanist_load_libraries(
      idx($system_config, 'load', array()),
      $must_load = true,
      $lib_source = 'the "load" setting in system config',
      $working_copy);

    // Load libraries in global 'load' config, as per "arc set-config load". We
    // need to fail softly if these break because errors would prevent the user
    // from running "arc set-config" to correct them.
    arcanist_load_libraries(
      idx($global_config, 'load', array()),
      $must_load = false,
      $lib_source = 'the "load" setting in global config',
      $working_copy);

    // Load libraries in ".arcconfig". Libraries here must load.
    arcanist_load_libraries(
      $working_copy->getConfig('load'),
      $must_load = true,
      $lib_source = 'the "load" setting in ".arcconfig"',
      $working_copy);
  }

  $user_config = ArcanistBaseWorkflow::readUserConfigurationFile();

  $config = $working_copy->getConfig('arcanist_configuration');
  if ($config) {
    $config = new $config();
  } else {
    $config = new ArcanistConfiguration();
  }

  $command = strtolower($args[0]);
  $args = array_slice($args, 1);
  $workflow = $config->buildWorkflow($command);
  if (!$workflow) {

    // If the user has an alias, like 'arc alias dhelp diff help', look it up
    // and substitute it. We do this only after trying to resolve the workflow
    // normally to prevent you from doing silly things like aliasing 'alias'
    // to something else.

    $aliases = ArcanistAliasWorkflow::getAliases($working_copy);
    list($new_command, $args) = ArcanistAliasWorkflow::resolveAliases(
      $command,
      $config,
      $args,
      $working_copy);

    $full_alias = idx($aliases, $command, array());
    $full_alias = implode(' ', $full_alias);

    // Run shell command aliases.

    if (ArcanistAliasWorkflow::isShellCommandAlias($new_command)) {
      $shell_cmd = substr($full_alias, 1);

      $console->writeLog(
        "[alias: 'arc %s' -> $ %s]",
        $command,
        $shell_cmd);

      if ($args) {
        $err = phutil_passthru('%C %Ls', $shell_cmd, $args);
      } else {
        $err = phutil_passthru('%C', $shell_cmd);
      }
      exit($err);
    }

    // Run arc command aliases.

    if ($new_command) {
      $workflow = $config->buildWorkflow($new_command);
      if ($workflow) {
        $console->writeLog(
          "[alias: 'arc %s' -> 'arc %s']\n",
          $command,
          $full_alias);
        $command = $new_command;
      }
    }

    if (!$workflow) {
      throw new ArcanistUsageException(
        "Unknown command '{$command}'. Try 'arc help'.");
    }
  }

  $workflow->setArcanistConfiguration($config);
  $workflow->setCommand($command);
  $workflow->setWorkingDirectory($working_directory);
  $workflow->parseArguments($args);

  if ($force_conduit_version) {
    $workflow->forceConduitVersion($force_conduit_version);
  }
  if ($conduit_timeout) {
    $workflow->setConduitTimeout($conduit_timeout);
  }

  $need_working_copy    = $workflow->requiresWorkingCopy();
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
    if ($need_working_copy && !$working_copy->getProjectRoot()) {
      throw new ArcanistUsageException(
        "This command must be run in a Git, Mercurial or Subversion working ".
        "copy.");
    }
    $workflow->setWorkingCopy($working_copy);
  }

  if ($force_conduit) {
    $conduit_uri = $force_conduit;
  } else {
    if ($working_copy->getConduitURI()) {
      $conduit_uri = $working_copy->getConduitURI();
    } else {
      $conduit_uri = idx($global_config, 'default');
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

  if ($need_conduit) {
    if (!$conduit_uri) {

      $message = phutil_console_format(
        "This command requires arc to connect to a Phabricator install, but ".
        "no Phabricator installation is configured. To configure a ".
        "Phabricator URI:\n\n".
        "  - set a default location with `arc set-config default <uri>`; or\n".
        "  - specify '--conduit-uri=uri' explicitly; or\n".
        "  - run 'arc' in a working copy with an '.arcconfig'.\n");

      $message = phutil_console_wrap($message);
      throw new ArcanistUsageException($message);
    }
    $workflow->establishConduit();
  }

  $hosts_config = idx($user_config, 'hosts', array());
  $host_config = idx($hosts_config, $conduit_uri, array());
  $user_name = idx($host_config, 'user');
  $certificate = idx($host_config, 'cert');

  $description = implode(' ', $original_argv);
  $credentials = array(
    'user'        => $user_name,
    'certificate' => $certificate,
    'description' => $description,
  );
  $workflow->setConduitCredentials($credentials);

  if ($need_auth) {
    if (!$user_name || !$certificate) {
      throw new ArcanistUsageException(
        phutil_console_format(
          "YOU NEED TO __INSTALL A CERTIFICATE__ TO LOGIN TO PHABRICATOR\n\n".
          "You are trying to connect to '{$conduit_uri}' but do not have ".
          "a certificate installed for this host. Run:\n\n".
          "      $ **arc install-certificate**\n\n".
          "...to install one."));
    }
    $workflow->authenticateConduit();
  }

  if ($need_repository_api || ($want_repository_api && $working_copy)) {
    $repository_api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity(
      $working_copy);
    $workflow->setRepositoryAPI($repository_api);
  }

  $listeners = $working_copy->getConfigFromAnySource('events.listeners');
  if ($listeners) {
    foreach ($listeners as $listener) {
      $console->writeLog(
        "Registering event listener '%s'.\n",
        $listener);
      try {
        id(new $listener())->register();
      } catch (PhutilMissingSymbolException $ex) {
        // Continue anwyay, since you may otherwise be unable to run commands
        // like `arc set-config events.listeners` in order to repair the damage
        // you've caused.
        $console->writeErr(
          "ERROR: Failed to load event listener '%s'!\n",
          $listener);
      }
    }
  }

  $config->willRunWorkflow($command, $workflow);
  $workflow->willRunWorkflow();
  $err = $workflow->run();
  $config->didRunWorkflow($command, $workflow, $err);
  exit((int)$err);

} catch (ArcanistUsageException $ex) {
  echo phutil_console_format(
    "**Usage Exception:** %s\n",
    $ex->getMessage());
  if ($config_trace_mode) {
    echo "\n";
    throw $ex;
  }

  exit(1);
} catch (Exception $ex) {
  if ($config_trace_mode) {
    throw $ex;
  }

  echo phutil_console_format(
    "**Exception**\n%s\n%s\n",
    $ex->getMessage(),
    "(Run with --trace for a full exception trace.)");

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
        "something similar."),
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
  echo "\nPHP CONFIGURATION ERRORS\n\n";
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
    $error = "Libraries specified by {$lib_source} are invalid; expected ".
             "a list. Check your configuration.";
    $console = PhutilConsole::getConsole();
    $console->writeErr("WARNING: %s\n", $error);
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
      "Loading phutil library from '%s'...\n",
      $location);

    $error = null;
    try {
      phutil_load_library($location);
    } catch (PhutilBootloaderException $ex) {
      $error = "Failed to load phutil library at location '{$location}'. ".
               "This library is specified by {$lib_source}. Check that the ".
               "setting is correct and the library is located in the right ".
               "place.";
      if ($must_load) {
        throw new ArcanistUsageException($error);
      } else {
        fwrite(STDERR, phutil_console_wrap('WARNING: '.$error."\n\n"));
      }
    } catch (PhutilLibraryConflictException $ex) {
      if ($ex->getLibrary() != 'arcanist') {
        throw $ex;
      }
      $arc_dir = dirname(dirname(__FILE__));
      $error =
        "You are trying to run one copy of Arcanist on another copy of ".
        "Arcanist. This operation is not supported. To execute Arcanist ".
        "operations against this working copy, run './bin/arc' (from the ".
        "current working copy) not some other copy of 'arc' (you ran one ".
        "from '{$arc_dir}').";
      throw new ArcanistUsageException($error);
    }
  }
}
