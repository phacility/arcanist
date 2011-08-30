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
 * You can extend this class and set "lint.xhpast.naminghook" in your
 * .arcconfig to have an opportunity to override lint results for symbol names.
 *
 * @task override Overriding Symbol Name Lint
 * @group lint
 */
abstract class ArcanistXHPASTLintNamingHook {

  final public function __construct() {
    // <empty>
  }

  /**
   * Callback invoked for each symbol, which can override the default
   * determination of name validity or accept it by returning $default. The
   * symbol types are: xhp-class, class, interface, function, method, parameter,
   * constant, and member.
   *
   * For example, if you want to ban all symbols with "quack" in them and
   * otherwise accept all the defaults, except allow any naming convention for
   * methods with "duck" in them, you might implement the method like this:
   *
   *   if (preg_match('/quack/i', $name)) {
   *     return 'Symbol names containing "quack" are forbidden.';
   *   }
   *   if ($type == 'method' && preg_match('/duck/i', $name)) {
   *     return null; // Always accept.
   *   }
   *   return $default;
   *
   * @param   string      The symbol type.
   * @param   string      The symbol name.
   * @param   string|null The default result from the main rule engine.
   * @return  string|null Null to accept the name, or a message to reject it
   *                      with. You should return the default value if you don't
   *                      want to specifically provide an override.
   * @task override
   */
  abstract public function lintSymbolName($type, $name, $default);

}
