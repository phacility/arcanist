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
    return array();
  }

  public function getPEP8Options() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $options = $working_copy->getConfig('lint.pep8.options');

    if ($options === null) {
      // W293 (blank line contains whitespace) is redundant when used
      // alongside TXT6, causing pain to python programmers.
      return '--ignore=W293';
    }

    return $options;
  }

  public function getPEP8Path() {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $prefix = $working_copy->getConfig('lint.pep8.prefix');
    $bin = $working_copy->getConfig('lint.pep8.bin');

    if ($bin === null && $prefix === null) {
      $bin = csprintf('/usr/bin/env python2.6 %s',
               phutil_get_library_root('arcanist').
               '/../externals/pep8/pep8.py');
    }
    else {
      if ($bin === null) {
        $bin = 'pep8';
      }

      if ($prefix !== null) {
        if (!Filesystem::pathExists($prefix.'/'.$bin)) {
          throw new ArcanistUsageException(
            "Unable to find PEP8 binary in a specified directory. Make sure ".
            "that 'lint.pep8.prefix' and 'lint.pep8.bin' keys are set ".
            "correctly. If you'd rather use a copy of PEP8 installed ".
            "globally, you can just remove these keys from your .arcconfig");
        }

        $bin = csprintf("%s/%s", $prefix, $bin);

        return $bin;
      }

      // Look for globally installed PEP8
      list($err) = exec_manual('which %s', $bin);
      if ($err) {
        throw new ArcanistUsageException(
          "PEP8 does not appear to be installed on this system. Install it ".
          "(e.g., with 'easy_install pep8') or configure ".
          "'lint.pep8.prefix' in your .arcconfig to point to the directory ".
          "where it resides.");
      }
    }

    return $bin;
  }

  public function lintPath($path) {
    $pep8_bin = $this->getPEP8Path();
    $options = $this->getPEP8Options();

    list($rc, $stdout) = exec_manual(
      "%C %C %s",
      $pep8_bin,
      $options,
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
