<?php

/**
 * Read configuration settings.
 *
 * @group workflow
 */
final class ArcanistGetConfigWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'get-config';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **get-config** -- [__name__ ...]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Reads an arc configuration option. With no argument, reads all
          options.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'argv',
    );
  }

  public function desiresRepositoryAPI() {
    return true;
  }

  public function run() {
    $argv = $this->getArgument('argv');

    $settings = new ArcanistSettings();

    $configs = array(
      'system'  => self::readSystemArcConfig(),
      'global'  => self::readGlobalArcConfig(),
      'project' => $this->getWorkingCopy()->getProjectConfig(),
      'local'   => $this->readLocalArcConfig(),
    );

    if ($argv) {
      $keys = $argv;
    } else {
      $keys = array_mergev(array_map('array_keys', $configs));
      $keys = array_unique($keys);
      sort($keys);
    }

    $multi = (count($keys) > 1);

    foreach ($keys as $key) {
      if ($multi) {
        echo "{$key}\n";
      }
      foreach ($configs as $name => $config) {
        switch ($name) {
          case 'project':
            // Respect older names in project config.
            $val = $this->getWorkingCopy()->getConfig($key);
            break;
          default:
            $val = idx($config, $key);
            break;
        }
        if ($val === null) {
          continue;
        }
        $val = $settings->formatConfigValueForDisplay($key, $val);
        printf("% 10.10s: %s\n", $name, $val);
      }
      if ($multi) {
        echo "\n";
      }
    }

    return 0;
  }

}
