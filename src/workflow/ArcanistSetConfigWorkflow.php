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
      **set-config** [__options__] -- __name__ __value__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Sets an arc configuration option.

          Options are either global (apply to all arc commands you invoke
          from the current user) or local (apply only to the current working
          copy).  By default, global configuration is written.  Use __--local__
          to write local configuration.

          Global values are written to '~/.arcrc' on Linux and Mac OS X, and an
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
        'help' => 'Set a local config value instead of a global one',
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

    $is_local = $this->getArgument('local');

    if ($is_local) {
      $config = $this->readLocalArcConfig();
      $which = 'local';
    } else {
      $config = self::readGlobalArcConfig();
      $which = 'global';
    }

    $key = $argv[0];
    $val = $argv[1];

    $old = null;
    if (array_key_exists($key, $config)) {
      $old = $config[$key];
    }

    if (!strlen($val)) {
      unset($config[$key]);
      if ($is_local) {
        $this->writeLocalArcConfig($config);
      } else {
        self::writeGlobalArcConfig($config);
      }

      if ($old === null) {
        echo "Deleted key '{$key}' from {$which} config.\n";
      } else {
        echo "Deleted key '{$key}' from {$which} config (was '{$old}').\n";
      }
    } else {
      $val = $this->parse($key, $val);

      $config[$key] = $val;
      if ($is_local) {
        $this->writeLocalArcConfig($config);
      } else {
        self::writeGlobalArcConfig($config);
      }

      $val = self::formatConfigValueForDisplay($val);
      $old = self::formatConfigValueForDisplay($old);

      if ($old === null) {
        echo "Set key '{$key}' = '{$val}' in {$which} config.\n";
      } else {
        echo "Set key '{$key}' = '{$val}' in {$which} config (was '{$old}').\n";
      }
    }

    return 0;
  }

  private function show() {
    $keys = array(
      'default'   => array(
        'help' =>
          'The URI of a Phabricator install to connect to by default, if '.
          'arc is run in a project without a Phabricator URI or run outside '.
          'of a project.',
        'example' => 'http://phabricator.example.com/',
      ),
      'load'      => array(
        'help' =>
          'A list of paths to phutil libraries that should be loaded at '.
          'startup. This can be used to make classes available, like lint or '.
          'unit test engines.',
        'example' => '["/var/arc/customlib/src"]',
      ),
      'lint.engine' => array(
        'help' =>
          'The name of a default lint engine to use, if no lint engine is '.
          'specified by the current project.',
        'example' => 'ExampleLintEngine',
      ),
      'unit.engine' => array(
        'help' =>
          'The name of a default unit test engine to use, if no unit test '.
          'engine is specified by the current project.',
        'example' => 'ExampleUnitTestEngine',
      ),
    );

    $config = self::readGlobalArcConfig();

    foreach ($keys as $key => $spec) {
      $type = $this->getType($key);

      $value = idx($config, $key);
      $value = self::formatConfigValueForDisplay($value);

      echo phutil_console_format("**__%s__** (%s)\n\n", $key, $type);
      echo phutil_console_format("           Example: %s\n", $spec['example']);
      if (strlen($value)) {
        echo phutil_console_format("    Global Setting: %s\n", $value);
      }
      echo "\n";
      echo phutil_console_wrap($spec['help'], 4);
      echo "\n\n\n";
    }

    return 0;
  }

  private function getType($key) {
    static $types = array(
      'load'  => 'list',
    );

    return idx($types, $key, 'string');
  }

  private function parse($key, $val) {
    $type = $this->getType($key);

    switch ($type) {
      case 'string':
        return $val;
      case 'list':
        $val = json_decode($val, true);
        if (!is_array($val)) {
          $example = '["apple", "banana", "cherry"]';
          throw new ArcanistUsageException(
            "Value for key '{$key}' must be specified as a JSON-encoded ".
            "list. Example: {$example}");
        }
        return $val;
      default:
        throw new Exception("Unknown config key type '{$type}'!");
    }
  }

}
