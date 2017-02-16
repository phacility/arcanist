<?php

/**
 * Write configuration settings.
 */
final class ArcanistSetConfigWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'set-config';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **set-config** [__options__] -- __name__ __value__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Sets an arc configuration option.

          Options are either user (apply to all arc commands you invoke
          from the current user) or local (apply only to the current working
          copy). By default, user configuration is written. Use __--local__
          to write local configuration.

          User values are written to '~/.arcrc' on Linux and Mac OS X, and an
          undisclosed location on Windows. Local values are written to an arc
          directory under either .git, .hg, or .svn as appropriate.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'local' => array(
        'help' => pht('Set a local config value instead of a user one.'),
      ),
      '*' => 'argv',
    );
  }

  public function requiresRepositoryAPI() {
    return $this->getArgument('local');
  }

  public function run() {
    $argv = $this->getArgument('argv');
    if (count($argv) != 2) {
      throw new ArcanistUsageException(
        pht('Specify a key and a value.'));
    }
    $configuration_manager = $this->getConfigurationManager();

    $is_local = $this->getArgument('local');

    if ($is_local) {
      $config = $configuration_manager->readLocalArcConfig();
      $which = 'local';
    } else {
      $config = $configuration_manager->readUserArcConfig();
      $which = 'user';
    }

    $key = $argv[0];
    $val = $argv[1];

    $settings = new ArcanistSettings();

    $console = PhutilConsole::getConsole();

    if (!$settings->getHelp($key)) {
      $warning = tsprintf(
        "**%s:** %s\n",
        pht('Warning'),
        pht(
          'The configuration key "%s" is not recognized by arc. It may '.
          'be misspelled or out of date.',
          $key));
      $console->writeErr('%s', $warning);
    }

    $old = null;
    if (array_key_exists($key, $config)) {
      $old = $config[$key];
    }

    if (!strlen($val)) {
      unset($config[$key]);
      if ($is_local) {
        $configuration_manager->writeLocalArcConfig($config);
      } else {
        $configuration_manager->writeUserArcConfig($config);
      }

      $old = $settings->formatConfigValueForDisplay($key, $old);

      if ($old === null) {
        $message = pht(
          'Deleted key "%s" from %s config.',
          $key,
          $which);
      } else {
        $message = pht(
          'Deleted key "%s" from %s config (was %s).',
          $key,
          $which,
          $old);
      }
      $console->writeOut('%s', tsprintf("%s\n", $message));
    } else {
      $val = $settings->willWriteValue($key, $val);

      $config[$key] = $val;
      if ($is_local) {
        $configuration_manager->writeLocalArcConfig($config);
      } else {
        $configuration_manager->writeUserArcConfig($config);
      }

      $val = $settings->formatConfigValueForDisplay($key, $val);
      $old = $settings->formatConfigValueForDisplay($key, $old);

      if ($old === null) {
        $message = pht(
          'Set key "%s" = %s in %s config.',
          $key,
          $val,
          $which);
      } else {
        $message = pht(
          'Set key "%s" = %s in %s config (was %s).',
          $key,
          $val,
          $which,
          $old);
      }
      $console->writeOut('%s', tsprintf("%s\n", $message));
    }

    return 0;
  }

}
