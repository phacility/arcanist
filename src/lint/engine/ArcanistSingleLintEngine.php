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
 * Run a single linter on every path unconditionally. This is a glue engine for
 * linters like @{class:ArcanistScriptAndRegexLintEngine}, if you are averse to
 * writing a phutil library. Your linter will receive every path, including
 * paths which have been moved or deleted.
 *
 * Set which linter should be run by configuring `lint.engine.single.linter` in
 * `.arcconfig` or user config.
 *
 * @group linter
 */
final class ArcanistSingleLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $key = 'lint.engine.single.linter';
    $linter_name = $this->getWorkingCopy()->getConfigFromAnySource($key);

    if (!$linter_name) {
      throw new ArcanistUsageException(
        "You must configure '{$key}' with the name of a linter in order to ".
        "use ArcanistSingleLintEngine.");
    }

    if (!class_exists($linter_name)) {
      throw new ArcanistUsageException(
        "Linter '{$linter_name}' configured in '{$key}' does not exist!");
    }

    if (!is_subclass_of($linter_name, 'ArcanistLinter')) {
      throw new ArcanistUsageException(
        "Linter '{$linter_name}' configured in '{$key}' MUST be a subclass of ".
        "ArcanistLinter.");
    }

    $linter = newv($linter_name, array());
    foreach ($this->getPaths() as $path) {
      $linter->addPath($path);
    }

    return array($linter);
  }
}
