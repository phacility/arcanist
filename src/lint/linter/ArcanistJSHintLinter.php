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
 * Uses "JSHint" to detect errors and potential problems in JavaScript code.
 * To use this linter, you must install jshint through NPM (Node Package
 * Manager). You can configure different JSHint options on a per-file basis.
 *
 * If you have NodeJS installed you should be able to install jshint with
 * ##npm install jshint -g## (don't forget the -g flag or NPM will install
 * the package locally). If your system is unusual, you can manually specify
 * the location of jshint and its dependencies by configuring these keys in
 * your .arcconfig:
 *
 *   lint.jshint.prefix
 *   lint.jshint.bin
 *
 * If you want to configure custom options for your project, create a JSON
 * file with these options and add the path to the file to your .arcconfig
 * by configuring this key:
 *
 *   lint.jshint.config
 *
 * Example JSON file (config.json):
 *
 * {
 *     "predef": [    // Custom globals
 *       "myGlobalVariable",
 *       "anotherGlobalVariable"
 *     ],
 *
 *     "es5": true,   // Allow ES5 syntax
 *     "strict": true // Require strict mode
 * }
 *
 * For more options see http://www.jshint.com/options/.
 *
 * @group linter
 */
final class ArcanistJSHintLinter extends ArcanistLinter {

  const JSHINT_ERROR = 1;

  public function getLinterName() {
    return 'JSHint';
  }

  public function getLintSeverityMap() {
    return array(
      self::JSHINT_ERROR => ArcanistLintSeverity::SEVERITY_ERROR
    );
  }

  public function getLintNameMap() {
    return array(
      self::JSHINT_ERROR => "JSHint Error"
    );
  }

  public function getJSHintOptions() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $options = '--reporter '.dirname(realpath(__FILE__)).'/reporter.js';
    $config = $working_copy->getConfig('lint.jshint.config');

    if ($config !== null) {
      $config = Filesystem::resolvePath($config, $working_copy->getProjectRoot());

      if (!Filesystem::pathExists($config)) {
        throw new ArcanistUsageException(
          "Unable to find custom options file defined by 'lint.jshint.config'. ".
          "Make sure that the path is correct.");
      }

      $options .= ' --config '.$config;
    }

    return $options;
  }

  private function getJSHintPath() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.jshint.prefix');
    $bin = $working_copy->getConfig('lint.jshint.bin');

    if ($bin === null) {
      $bin = "jshint";
    }

    if ($prefix !== null) {
      $bin = $prefix."/".$bin;

      if (!Filesystem::pathExists($bin)) {
        throw new ArcanistUsageException(
          "Unable to find JSHint binary in a specified directory. Make sure ".
          "that 'lint.jshint.prefix' and 'lint.jshint.bin' keys are set ".
          "correctly. If you'd rather use a copy of JSHint installed globally, ".
          "you can just remove these keys from your .arcconfig");
      }

      return $bin;
    }

    // Look for globally installed JSHint
    $cmd = (phutil_is_windows()) ? 'where %s' : 'which %s';
    list($err) = exec_manual($cmd, $bin);
    if ($err) {
      throw new ArcanistUsageException(
        "JSHint does not appear to be installed on this system. Install it ".
        "(e.g., with 'npm install jshint -g') or configure ".
        "'lint.jshint.prefix' in your .arcconfig to point to the directory ".
        "where it resides.");
    }

    return $bin;
  }

  public function willLintPaths(array $paths) {
    $jshint_bin = $this->getJSHintPath();
    $jshint_options = $this->getJSHintOptions();
    $futures = array();

    foreach ($paths as $path) {
      $filepath = $this->getEngine()->getFilePathOnDisk($path);
      $futures[$path] = new ExecFuture("{$jshint_bin} {$filepath} ${jshint_options}");
    }

    foreach (Futures($futures)->limit(8) as $path => $future) {
      $this->results[$path] = $future->resolve();
    }
  }

  public function lintPath($path) {
    list($rc, $stdout) = $this->results[$path];

    if ($rc === 0) {
      return;
    }

    $errors = json_decode($stdout);
    if (!is_array($errors)) {
      // Something went wrong and we can't decode the output. Exit abnormally.
      throw new ArcanistUsageException(
        "JSHint returned output we can't parse. Check that your JSHint installation.\n".
        "Output:\n".
        $stdout);
    }

    foreach ($errors as $err) {
      $this->raiseLintAtLine(
        $err->line,
        $err->col,
        self::JSHINT_ERROR,
        $err->reason);
    }
  }
}
