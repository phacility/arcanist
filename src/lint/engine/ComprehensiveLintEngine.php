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
 * Basic lint engine which just applies several linters based on the file types
 *
 * @group linter
 */
final class ComprehensiveLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $linters = array();

    $paths = $this->getPaths();

    foreach ($paths as $key => $path) {
      if (!$this->pathExists($path)) {
        unset($paths[$key]);
      }
      if (preg_match('@^externals/@', $path)) {
        // Third-party stuff lives in /externals/; don't run lint engines
        // against it.
        unset($paths[$key]);
      }
    }

    $text_paths = preg_grep('/\.(php|css|hpp|cpp|l|y)$/', $paths);
    $linters[] = id(new ArcanistGeneratedLinter())->setPaths($text_paths);
    $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);
    $linters[] = id(new ArcanistTextLinter())->setPaths($text_paths);

    $linters[] = id(new ArcanistFilenameLinter())->setPaths($paths);

    $linters[] = id(new ArcanistXHPASTLinter())
      ->setPaths(preg_grep('/\.php$/', $paths));

    $linters[] = id(new ArcanistApacheLicenseLinter())
      ->setPaths(preg_grep('/\.(php|cpp|hpp|l|y)$/', $paths));

    $py_paths = preg_grep('/\.py$/', $paths);
    $linters[] = id(new ArcanistPyFlakesLinter())->setPaths($py_paths);
    $linters[] = id(new ArcanistPEP8Linter())->setPaths($py_paths);

    $linters[] = id(new ArcanistRubyLinter())
      ->setPaths(preg_grep('/\.rb$/', $paths));

    $linters[] = id(new ArcanistJSHintLinter())
      ->setPaths(preg_grep('/\.js$/', $paths));

    $linters = array_merge($linters, $this->buildJSLinters($paths));

    return $linters;
  }

}
