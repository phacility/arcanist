<?php

/**
 * Manages aliases for commands with options.
 */
final class ArcanistAliasWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'alias';
  }

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

          If you start an alias with "!", the remainder of the alias will be
          invoked as a shell command. For example, if you want to implement
          'arc ls', you can do so like this:

            arc alias ls '!ls'

          You can now run "arc ls" and it will behave like "ls". Of course, this
          example is silly and would make your life worse.

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

  public static function getAliases(
    ArcanistConfigurationManager $configuration_manager) {

    $working_copy_config_aliases =
      $configuration_manager->getProjectConfig('aliases');
    if (!$working_copy_config_aliases) {
      $working_copy_config_aliases = array();
    }
    $user_config_aliases = idx(
      $configuration_manager->readUserConfigurationFile(),
      'aliases',
      array());
    return $user_config_aliases + $working_copy_config_aliases;
  }

  private function writeAliases(array $aliases) {
    $config = $this->getConfigurationManager()->readUserConfigurationFile();
    $config['aliases'] = $aliases;
    $this->getConfigurationManager()->writeUserConfigurationFile($config);
  }

  public function run() {
    $aliases = self::getAliases($this->getConfigurationManager());

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

  public static function isShellCommandAlias($command) {
    return preg_match('/^!/', $command);
  }

  public static function resolveAliases(
    $command,
    ArcanistConfiguration $config,
    array $argv,
    ArcanistConfigurationManager $configuration_manager) {

    $aliases = ArcanistAliasWorkflow::getAliases($configuration_manager);
    if (!isset($aliases[$command])) {
      return array(null, $argv);
    }

    $new_command = head($aliases[$command]);

    if (self::isShellCommandAlias($new_command)) {
      return array($new_command, $argv);
    }

    $workflow = $config->buildWorkflow($new_command);
    if (!$workflow) {
      return array(null, $argv);
    }

    $alias_argv = array_slice($aliases[$command], 1);
    foreach (array_reverse($alias_argv) as $alias_arg) {
      if (!in_array($alias_arg, $argv)) {
        array_unshift($argv, $alias_arg);
      }
    }

    return array($new_command, $argv);
  }

}
