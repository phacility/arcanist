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
 * Uses "Ruby" to detect various errors in Ruby code.
 *
 * @group linter
 */
final class ArcanistRubyLinter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'Ruby';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array();
  }

  private function getRubyPath() {
    $ruby_bin = "ruby";

    // Use the Ruby prefix specified in the config file
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.ruby.prefix');
    if ($prefix !== null) {
      $ruby_bin = $prefix . $ruby_bin;
    }

    if (!Filesystem::pathExists($ruby_bin)) {

      list($err) = exec_manual('which %s', $ruby_bin);
      if ($err) {
        throw new ArcanistUsageException(
          "Ruby does not appear to be installed on this system. Install it or ".
          "add 'lint.ruby.prefix' in your .arcconfig to point to ".
          "the directory where it resides.");
      }
    }

    return $ruby_bin;
  }

  private function getMessageCodeSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function lintPath($path) {
    $rubyp = $this->getRubyPath();
    $f = new ExecFuture("%s -wc", $rubyp);
    $f->write($this->getData($path));
    list($err, $stdout, $stderr) = $f->resolve();
    if ($err === 0 ) {
      return;
    }

    $lines = explode("\n", $stderr);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match("/(.*?):(\d+): (.*?)$/", $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $code = head(explode(',', $matches[3]));

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setName($this->getLinterName() . " " . $code);
      $message->setDescription($matches[3]);
      $message->setSeverity($this->getMessageCodeSeverity($code));
      $this->addLintMessage($message);
    }
  }

}
