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

/**
 * Show which revision or revisions are in the working copy.
 *
 * @group workflow
 */
final class ArcanistAliasWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **alias**
      **alias** __command__
      **alias** __command__ __target__ -- [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Create an alias from __command__ to __target__ (optionally, with
          __options__). For example:

            arc alias fpatch patch -- --force

          ...will create a new 'arc' command, 'arc fpatch', which invokes
          'arc patch --force ...' when run. NOTE: use "--" before specifying
          options!

          You can not overwrite builtins, including 'alias' itself. The builtin
          will always execute, even if it was added after your alias.

          To remove an alias, run:

            arc alias fpatch

          Without any arguments, 'arc alias' will list aliases.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'argv',
    );
  }

  public static function getAliases() {
    $config = self::readUserConfigurationFile();
    return idx($config, 'aliases', array());
  }

  private function writeAliases(array $aliases) {
    $config = self::readUserConfigurationFile();
    $config['aliases'] = $aliases;
    self::writeUserConfigurationFile($config);
  }

  public function run() {

    $aliases = self::getAliases();

    $argv = $this->getArgument('argv');
    if (count($argv) == 0) {
      if ($aliases) {
        foreach ($aliases as $alias => $binding) {
          echo phutil_console_format(
            "**%s** %s\n",
            $alias,
            implode(' ' , $binding));
        }
      } else {
        echo "You haven't defined any aliases yet.\n";
      }
    } else if (count($argv) == 1) {
      if (empty($aliases[$argv[0]])) {
        echo "No alias '{$argv[0]}' to remove.\n";
      } else {
        echo phutil_console_format(
          "'**arc %s**' is currently aliased to '**arc %s**'.",
          $argv[0],
          implode(' ', $aliases[$argv[0]]));
        $ok = phutil_console_confirm('Delete this alias?');
        if ($ok) {
          $was = implode(' ', $aliases[$argv[0]]);
          unset($aliases[$argv[0]]);
          $this->writeAliases($aliases);
          echo "Unaliased '{$argv[0]}' (was '{$was}').\n";
        } else {
          throw new ArcanistUserAbortException();
        }
      }
    } else {
      $arc_config = $this->getArcanistConfiguration();

      if ($arc_config->buildWorkflow($argv[0])) {
        throw new ArcanistUsageException(
          "You can not create an alias for '{$argv[0]}' because it is a ".
          "builtin command. 'arc alias' can only create new commands.");
      }

      $aliases[$argv[0]] = array_slice($argv, 1);

      echo phutil_console_format(
        "Aliased '**arc %s**' to '**arc %s**'.\n",
        $argv[0],
        implode(' ', $aliases[$argv[0]]));

      $this->writeAliases($aliases);
    }

    return 0;
  }

  public static function resolveAliases(
    $command,
    ArcanistConfiguration $config,
    array $argv) {

    $aliases = ArcanistAliasWorkflow::getAliases();
    if (!isset($aliases[$command])) {
      return array(null, $argv);
    }

    $new_command = head($aliases[$command]);
    $workflow = $config->buildWorkflow($new_command);
    if (!$workflow) {
      return array(null, $argv);
    }

    $alias_argv = array_slice($aliases[$command], 1);
    foreach ($alias_argv as $alias_arg) {
      if (!in_array($alias_arg, $argv)) {
        array_unshift($argv, $alias_arg);
      }
    }

    return array($new_command, $argv);
  }

}
