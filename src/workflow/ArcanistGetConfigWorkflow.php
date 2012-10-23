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
