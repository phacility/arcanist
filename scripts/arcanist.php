#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
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

phutil_require_module('phutil', 'conduit/client');
phutil_require_module('phutil', 'console');
phutil_require_module('phutil', 'future/exec');
phutil_require_module('phutil', 'filesystem');
phutil_require_module('phutil', 'symbols');

phutil_require_module('arcanist', 'exception/usage');
phutil_require_module('arcanist', 'configuration');
phutil_require_module('arcanist', 'workingcopyidentity');
phutil_require_module('arcanist', 'repository/api/base');

ini_set('memory_limit', -1);

$config_trace_mode = false;
$force_conduit = null;
$args = array_slice($argv, 1);
$load = array();
$matches = null;
foreach ($args as $key => $arg) {
  if ($arg == '--') {
    break;
  } else if ($arg == '--trace') {
    unset($args[$key]);
    $config_trace_mode = true;
  } else if ($arg == '--no-ansi') {
    unset($args[$key]);
    PhutilConsoleFormatter::disableANSI(true);
  } else if (preg_match('/^--load-phutil-library=(.*)$/', $arg, $matches)) {
    unset($args[$key]);
    $load['?'] = $matches[1];
  } else if (preg_match('/^--conduit-uri=(.*)$/', $arg, $matches)) {
    unset($args[$key]);
    $force_conduit = $matches[1];
  }
}

// The POSIX extension is not available by default in some PHP installs.
if (function_exists('posix_isatty') && !posix_isatty(STDOUT)) {
  PhutilConsoleFormatter::disableANSI(true);
}

$args = array_values($args);
$working_directory = getcwd();

try {

  if ($config_trace_mode) {
    PhutilServiceProfiler::installEchoListener();
  }

  if (!$args) {
    throw new ArcanistUsageException("No command provided. Try 'arc help'.");
  }

  $working_copy = ArcanistWorkingCopyIdentity::newFromPath($working_directory);
  if ($load) {
    $libs = $load;
  } else {
    $libs = $working_copy->getConfig('phutil_libraries');
  }
  if ($libs) {
    foreach ($libs as $name => $location) {

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

      $resolved = false;

      // Check inside the working copy.
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

      if ($config_trace_mode) {
        echo "Loading phutil library '{$name}' from '{$location}'...\n";
      }

      try {
        phutil_load_library($location);
      } catch (PhutilBootloaderException $ex) {
        $error_msg = sprintf(
          'Failed to load library "%s" at location "%s". Please check the '.
          '"phutil_libraries" setting in your .arcconfig file. Refer to page '.
          'http://phabricator.com/docs/arcanist/article/'.
          'Setting_Up_.arcconfig.html for more info.',
          $name,
          $location);
        throw new ArcanistUsageException($error_msg);
      } catch (PhutilLibraryConflictException $ex) {
        if ($ex->getLibrary() != 'arcanist') {
          throw $ex;
        }

        $arc_dir = dirname(dirname(__FILE__));
        $error_msg =
          "You are trying to run one copy of Arcanist on another copy of ".
          "Arcanist. This operation is not supported. To execute Arcanist ".
          "operations against this working copy, run './bin/arc' (from the ".
          "current working copy) not some other copy of 'arc' (you ran one ".
          "from '{$arc_dir}').";

        throw new ArcanistUsageException($error_msg);
      }
    }
  }

  $user_config = ArcanistBaseWorkflow::readUserConfigurationFile();

  $config = $working_copy->getConfig('arcanist_configuration');
  if ($config) {
    PhutilSymbolLoader::loadClass($config);
    $config = new $config();
  } else {
    $config = new ArcanistConfiguration();
  }

  $command = strtolower($args[0]);
  $workflow = $config->buildWorkflow($command);
  if (!$workflow) {
    throw new ArcanistUsageException(
      "Unknown command '{$command}'. Try 'arc help'.");
  }
  $workflow->setArcanistConfiguration($config);
  $workflow->setCommand($command);
  $workflow->setWorkingDirectory($working_directory);
  $workflow->parseArguments(array_slice($args, 1));

  $need_working_copy    = $workflow->requiresWorkingCopy();
  $need_conduit         = $workflow->requiresConduit();
  $need_auth            = $workflow->requiresAuthentication();
  $need_repository_api  = $workflow->requiresRepositoryAPI();

  $need_conduit       = $need_conduit ||
                        $need_auth;
  $need_working_copy  = $need_working_copy ||
                        $need_conduit ||
                        $need_repository_api;

  if ($need_working_copy) {
    if (!$working_copy->getProjectRoot()) {
      throw new ArcanistUsageException(
        "There is no '.arcconfig' file in this directory or any parent ".
        "directory. Create a '.arcconfig' file to configure this project ".
        "for use with Arcanist.");
    }
    $workflow->setWorkingCopy($working_copy);
  }


  if ($force_conduit) {
    $conduit_uri = $force_conduit;
  } else {
    $conduit_uri = $working_copy->getConduitURI();
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
      throw new ArcanistUsageException(
        "No Conduit URI is specified in the .arcconfig file for this project. ".
        "Specify the Conduit URI for the host Differential is running on.");
    }
    $workflow->establishConduit();
  }

  $hosts_config = idx($user_config, 'hosts', array());
  $host_config = idx($hosts_config, $conduit_uri, array());
  $user_name = idx($host_config, 'user');
  $certificate = idx($host_config, 'cert');

  $description = implode(' ', $argv);
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

  if ($need_repository_api) {
    $repository_api = ArcanistRepositoryAPI::newAPIFromWorkingCopyIdentity(
      $working_copy);
    $workflow->setRepositoryAPI($repository_api);
  }

  $listeners = $working_copy->getConfig('events.listeners');
  if ($listeners) {
    foreach ($listeners as $listener) {
      id(new $listener())->register();
    }
  }

  $config->willRunWorkflow($command, $workflow);
  $workflow->willRunWorkflow();
  $err = $workflow->run();
  $config->didRunWorkflow($command, $workflow, $err);
  exit($err);

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
    "\n**Exception:**\n%s\n%s\n",
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
  $min_version = '5.2.0';
  $cur_version = phpversion();
  if (version_compare($cur_version, $min_version, '<')) {
    die_with_bad_php(
      "You are running PHP version '{$cur_version}', which is older than ".
      "the minimum version, '{$min_version}'. Update to at least ".
      "'{$min_version}'.");
  }

  $need_functions = array(
    'json_decode' => '--without-json',
  );

  $problems = array();

  $config = null;
  $show_config = false;
  foreach ($need_functions as $fname => $flag) {
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

    if (strpos($config, $flag) !== false) {
      $show_config = true;
      $problems[] =
        "This build of PHP was compiled with the configure flag '{$flag}', ".
        "which means it does not have the function '{$fname}()'. This ".
        "function is required for arc to run. Rebuild PHP without this flag. ".
        "You may also be able to build or install the relevant extension ".
        "separately.";
    } else {
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
