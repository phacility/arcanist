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

class ArcanistApacheLicenseLinter extends ArcanistLinter {

  const LINT_NO_LICENSE_HEADER = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'APACHELICENSE';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
      self::LINT_NO_LICENSE_HEADER   => 'No License Header',
    );
  }

  public function lintPath($path) {
    $working_copy = $this->getEngine()->getWorkingCopy();
    $copyright_holder = $working_copy->getConfig('copyright_holder');

    if (!$copyright_holder) {
      throw new ArcanistUsageException(
        "This project uses the ArcanistApacheLicenseLinter, but does not ".
        "define a 'copyright_holder' in its .arcconfig.");
    }

    $year = date('Y');

    $maybe_php_or_script = '(#![^\n]+?[\n])?(<[?]php\s+?)?';
    $patterns = array(
      "@^{$maybe_php_or_script}//[^\n]*Copyright[^\n]*[\n]\s*@i",
      "@^{$maybe_php_or_script}/[*].*?Copyright.*?[*]/\s*@is",
      "@^{$maybe_php_or_script}\s*@",
    );

    $license = <<<EOLICENSE
/*
 * Copyright {$year} {$copyright_holder}
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


EOLICENSE;

    foreach ($patterns as $pattern) {
      $data = $this->getData($path);
      $matches = 0;
      if (preg_match($pattern, $data, $matches)) {
        $expect = rtrim(implode('', array_slice($matches, 1)))."\n\n".$license;
        $expect = ltrim($expect);
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
