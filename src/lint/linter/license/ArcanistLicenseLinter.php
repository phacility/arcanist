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

abstract class ArcanistLicenseLinter extends ArcanistLinter {
  const LINT_NO_LICENSE_HEADER = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
      self::LINT_NO_LICENSE_HEADER   => 'No License Header',
    );
  }

  /**
   * Given the name of the copyright holder, return appropriate license header
   * text.
   */
  abstract protected function getLicenseText($copyright_holder);
  /**
   * Return an array of regular expressions that, if matched, indicate
   * that a copyright header is required. The appropriate match will be
   * stripped from the input when comparing against the expected license.
   */
  abstract protected function getLicensePatterns();

  public function lintPath($path) {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $copyright_holder = $working_copy->getConfig('copyright_holder');

    if (!$copyright_holder) {
      return;
    }

    $patterns = $this->getLicensePatterns();
    $license = $this->getLicenseText($copyright_holder);

    $data = $this->getData($path);
    $matches = 0;

    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $data, $matches)) {
        $expect = rtrim(implode('', array_slice($matches, 1)))."\n".$license;
        if (rtrim($matches[0]) != rtrim($expect)) {
          $this->raiseLintAtOffset(
            0,
            self::LINT_NO_LICENSE_HEADER,
            'This file has a missing or out of date license header.',
            $matches[0],
            $expect);
        }
        break;
      }
    }
  }
}


