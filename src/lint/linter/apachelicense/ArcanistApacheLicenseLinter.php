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
 * Adds the Apache license to source files.
 *
 * @group linter
 */
class ArcanistApacheLicenseLinter extends ArcanistLicenseLinter {
  public function getLinterName() {
    return 'APACHELICENSE';
  }

  protected function getLicenseText($copyright_holder) {
    $year = date('Y');

    return <<<EOLICENSE

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
  }

  protected function getLicensePatterns() {
    $maybe_php_or_script = '(#![^\n]+?[\n])?(<[?]php\s+?)?';
    return array(
      "@^{$maybe_php_or_script}//[^\n]*Copyright[^\n]*[\n]\s*@i",

      // We need to be careful about matching after "/*", since otherwise we'll
      // end up in trouble on code like this, and consume the entire thing:
      //
      //  /* a */
      //  copyright();
      //  /* b */
      "@^{$maybe_php_or_script}/[*](?:[^*]|[*][^/])*?Copyright.*?[*]/\s*@is",
      "@^{$maybe_php_or_script}\s*@",
    );
  }
}
