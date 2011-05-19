<?php

/*
 * Copyright 2011 Facebook, Inc.
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
 * Uses "pep8.py" to enforce PEP8 rules for Python.
 *
 * @group linter
 */
class ArcanistPEP8Linter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'PEP8';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function getPEP8Options() {
    return null;
  }

  public function lintPath($path) {
    $pep8_bin = phutil_get_library_root('arcanist').
                  '/../externals/pep8/pep8.py';

    $options = $this->getPEP8Options();

    list($rc, $stdout) = exec_manual(
      "/usr/bin/env python2.6 %s {$options} %s",
      $pep8_bin,
      $this->getEngine()->getFilePathOnDisk($path));

    $lines = explode("\n", $stdout);
    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match('/^(.*?):(\d+):(\d+): (\S+) (.*)$/', $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }
      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[2]);
      $message->setChar($matches[3]);
      $message->setCode($matches[4]);
      $message->setName('PEP8 '.$matches[4]);
      $message->setDescription($matches[5]);
      if ($matches[4][0] == 'E') {
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
      } else {
        $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
      }
      $this->addLintMessage($message);
    }
  }

}
