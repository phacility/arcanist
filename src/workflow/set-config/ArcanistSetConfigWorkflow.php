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
 * Write configuration settings.
 *
 * @group workflow
 */
final class ArcanistSetConfigWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **set-config** __name__ __value__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Sets an arc configuration option.

          Values are written to '~/.arcrc' on Linux and Mac OS X, and an
          undisclosed location on Windows.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'argv',
    );
  }

  public function run() {
    $argv = $this->getArgument('argv');
    if (count($argv) != 2) {
      throw new ArcanistUsageException("Specify a key and a value.");
    }

    $config = self::readGlobalArcConfig();

    $key = $argv[0];
    $val = $argv[1];

    $old = null;
    if (array_key_exists($key, $config)) {
      $old = $config[$key];
    }

    if (!strlen($val)) {
      unset($config[$key]);
      self::writeGlobalArcConfig($config);

      if ($old === null) {
        echo "Deleted key '{$key}'.\n";
      } else {
        echo "Deleted key '{$key}' (was '{$old}').\n";
      }
    } else {
      $config[$key] = $val;
      self::writeGlobalArcConfig($config);

      if ($old === null) {
        echo "Set key '{$key}' = '{$val}'.\n";
      } else {
        echo "Set key '{$key}' = '{$val}' (was '{$old}').\n";
      }
    }

    return 0;
  }

}
