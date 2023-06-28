<?php

/**
 * Read configuration settings.
 */
final class ArcanistGetConfigWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'get-config';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **get-config** [__options__] -- [__name__ ...]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Reads an arc configuration option. With no argument, reads all
          options.

          With __--verbose__, shows detailed information about one or more
          options.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'verbose' => array(
        'help' => pht('Show detailed information about options.'),
      ),
      '*' => 'argv',
    );
  }

  public function desiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $argv = $this->getArgument('argv');
    $verbose = $this->getArgument('verbose');

    $settings = new ArcanistSettings();

    $configuration_manager = $this->getConfigurationManager();
    $configs = array(
      ArcanistConfigurationManager::CONFIG_SOURCE_LOCAL =>
        $configuration_manager->readLocalArcConfig(),
      ArcanistConfigurationManager::CONFIG_SOURCE_PROJECT =>
        $this->getWorkingCopyIdentity()->readProjectConfig(),
      ArcanistConfigurationManager::CONFIG_SOURCE_USER =>
        $configuration_manager->readUserArcConfig(),
      ArcanistConfigurationManager::CONFIG_SOURCE_SYSTEM =>
        $configuration_manager->readSystemArcConfig(),
      ArcanistConfigurationManager::CONFIG_SOURCE_DEFAULT =>
        $configuration_manager->readDefaultConfig(),
    );

    if ($argv) {
      $keys = $argv;
    } else {
      $keys = array_mergev(array_map('array_keys', $configs));
      $keys = array_merge($keys, $settings->getAllKeys());
      $keys = array_unique($keys);
      sort($keys);
    }

    $console = PhutilConsole::getConsole();
    $multi = (count($keys) > 1);

    foreach ($keys as $key) {
      $console->writeOut("**%s**\n\n", $key);

      if ($verbose) {
        $help = $settings->getHelp($key);
        if (!$help) {
          $help = pht(
            '(This configuration value is not recognized by arc. It may '.
            'be misspelled or out of date.)');
        }

        $console->writeOut("%s\n\n", phutil_console_wrap($help, 4));

        $console->writeOut(
          "%s: %s\n\n",
          sprintf('% 20.20s', pht('Example Value')),
          $settings->getExample($key));

      }

      $values = array();
      foreach ($configs as $config_key => $config) {
        if (array_key_exists($key, $config)) {
          $values[$config_key] = $config[$key];
        } else {
          // If we didn't find a value, look for a legacy value.
          $source_project = ArcanistConfigurationManager::CONFIG_SOURCE_PROJECT;
          if ($config_key === $source_project) {
            $legacy_name = $settings->getLegacyName($key);
            if (array_key_exists($legacy_name, $config)) {
              $values[$config_key] = $config[$legacy_name];
            }
          }
        }
      }

      $console->writeOut(
        '%s: ',
        sprintf('% 20.20s', pht('Current Value')));

      if ($values) {
        $value = head($values);
        $value = $settings->formatConfigValueForDisplay($key, $value);
        $console->writeOut("%s\n", $value);
      } else {
        $console->writeOut("-\n");
      }

      $console->writeOut(
        '%s: ',
        sprintf('% 20.20s', pht('Current Source')));

      if ($values) {
        $source = head_key($values);
        $console->writeOut("%s\n", $source);
      } else {
        $console->writeOut("-\n");
      }

      if ($verbose) {
        $console->writeOut("\n");

        foreach ($configs as $name => $config) {
          $have_value = false;
          if (array_key_exists($name, $values)) {
            $have_value = true;
            $value = $values[$name];
          }

          $console->writeOut(
            '%s: ',
            sprintf('% 20.20s', pht('%s Value', $name)));

          if ($have_value) {
            $console->writeOut(
              "%s\n",
              $settings->formatConfigValueForDisplay($key, $value));
          } else {
            $console->writeOut("-\n");
          }
        }
      }

      if ($multi) {
        echo "\n";
      }
    }

    if (!$verbose) {
      $console->writeOut(
        "(%s)\n",
        pht('Run with %s for more details.', '--verbose'));
    }

    return 0;
  }

}
