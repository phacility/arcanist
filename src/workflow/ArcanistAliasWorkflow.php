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
    $sources = $configuration_manager->getConfigFromAllSources('aliases');

    $aliases = array();
    foreach ($sources as $source) {
      $aliases += $source;
    }

    return $aliases;
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
      $this->printAliases($aliases);
    } else if (count($argv) == 1) {
      $this->removeAlias($aliases, $argv[0]);
    } else {
      $arc_config = $this->getArcanistConfiguration();
      $alias = $argv[0];

      if ($arc_config->buildWorkflow($alias)) {
        throw new ArcanistUsageException(
          pht(
            'You can not create an alias for "%s" because it is a '.
            'builtin command. "%s" can only create new commands.',
            "arc {$alias}",
            'arc alias'));
      }

      $new_alias = array_slice($argv, 1);

      $command = implode(' ', $new_alias);
      if (self::isShellCommandAlias($command)) {
        echo tsprintf(
          "%s\n",
          pht(
            'Aliased "%s" to shell command "%s".',
            "arc {$alias}",
            substr($command, 1)));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Aliased "%s" to "%s".',
            "arc {$alias}",
            "arc {$command}"));
      }

      $aliases[$alias] = $new_alias;
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

    $aliases = self::getAliases($configuration_manager);
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

  private function printAliases(array $aliases) {
    if (!$aliases) {
      echo tsprintf(
        "%s\n",
        pht('You have not defined any aliases yet.'));
      return;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn('input', array('title' => pht('Alias')))
      ->addColumn('command', array('title' => pht('Command')))
      ->addColumn('type', array('title' => pht('Type')));

    ksort($aliases);

    foreach ($aliases as $alias => $binding) {
      $command = implode(' ', $binding);
      if (self::isShellCommandAlias($command)) {
        $command = substr($command, 1);
        $type = pht('Shell Command');
      } else {
        $command = "arc {$command}";
        $type = pht('Arcanist Command');
      }

      $row = array(
        'input' => "arc {$alias}",
        'type' => $type,
        'command' => $command,
      );

      $table->addRow($row);
    }

    $table->draw();
  }

  private function removeAlias(array $aliases, $alias) {
    if (empty($aliases[$alias])) {
      echo tsprintf(
        "%s\n",
        pht('No alias "%s" to remove.', $alias));
      return;
    }

    $command = implode(' ', $aliases[$alias]);

    if (self::isShellCommandAlias($command)) {
      echo tsprintf(
        "%s\n",
        pht(
          '"%s" is currently aliased to shell command "%s".',
          "arc {$alias}",
          substr($command, 1)));
    } else {
      echo tsprintf(
        "%s\n",
        pht(
          '"%s" is currently aliased to "%s".',
          "arc {$alias}",
          "arc {$command}"));
    }


    $ok = phutil_console_confirm(pht('Delete this alias?'));
    if (!$ok) {
      throw new ArcanistUserAbortException();
    }

    unset($aliases[$alias]);
    $this->writeAliases($aliases);

    echo tsprintf(
      "%s\n",
      pht(
        'Removed alias "%s".',
        "arc {$alias}"));
  }

}
