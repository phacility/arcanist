<?php

/**
 * Write configuration settings.
 *
 * @group workflow
 */
final class ArcanistSetConfigWorkflow extends ArcanistBaseWorkflow {

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
          copy).  By default, user configuration is written.  Use __--local__
          to write local configuration.

          User values are written to '~/.arcrc' on Linux and Mac OS X, and an
          undisclosed location on Windows.  Local values are written to an arc
          directory under either .git, .hg, or .svn as appropriate.

          With __--show__, a description of supported configuration values
          is shown.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'show' => array(
        'help' => 'Show available configuration values.',
      ),
      'local' => array(
        'help' => 'Set a local config value instead of a user one',
      ),
      '*' => 'argv',
    );
  }

  public function requiresRepositoryAPI() {
    return $this->getArgument('local');
  }

  public function run() {
    if ($this->getArgument('show')) {
      return $this->show();
    }

    $argv = $this->getArgument('argv');
    if (count($argv) != 2) {
      throw new ArcanistUsageException(
        "Specify a key and a value, or --show.");
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
        echo "Deleted key '{$key}' from {$which} config.\n";
      } else {
        echo "Deleted key '{$key}' from {$which} config (was {$old}).\n";
      }
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
        echo "Set key '{$key}' = {$val} in {$which} config.\n";
      } else {
        echo "Set key '{$key}' = {$val} in {$which} config (was {$old}).\n";
      }
    }

    return 0;
  }

  private function show() {
    $config_manager = $this->getConfigurationManager();

    $settings = new ArcanistSettings();

    $keys = $settings->getAllKeys();
    sort($keys);
    foreach ($keys as $key) {
      $type = $settings->getType($key);
      $example = $settings->getExample($key);
      $help = $settings->getHelp($key);

      $config = $config_manager->getConfigFromAllSources($key);

      $source = head_key($config);

      $value = head($config);
      $value = $settings->formatConfigValueForDisplay($key, $value);

      echo phutil_console_format("**__%s__** (%s)\n\n", $key, $type);
      if ($example !== null) {
        echo phutil_console_format("           Example: %s\n", $example);
      }
      if (strlen($value)) {
        if (strlen($source)) {
          $source = pht('(from %s config)', $source);
        }
        echo phutil_console_format(
          "   Current Setting: %s %s\n",
          $value,
          $source);
      }
      echo "\n";
      echo phutil_console_wrap($help, 4);
      echo "\n\n\n";
    }

    return 0;
  }

}
