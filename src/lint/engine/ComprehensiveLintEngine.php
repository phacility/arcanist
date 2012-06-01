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

    $generated_linter = new ArcanistGeneratedLinter();
    $linters[] = $generated_linter;

    $nolint_linter = new ArcanistNoLintLinter();
    $linters[] = $nolint_linter;

    $text_linter = new ArcanistTextLinter();
    $linters[] = $text_linter;
    foreach ($paths as $path) {
      $is_text = false;
      if (preg_match('/\.(php|css|hpp|cpp|l|y)$/', $path)) {
        $is_text = true;
      }
      if ($is_text) {
        $generated_linter->addPath($path);
        $generated_linter->addData($path, $this->loadData($path));

        $nolint_linter->addPath($path);
        $nolint_linter->addData($path, $this->loadData($path));

        $text_linter->addPath($path);
        $text_linter->addData($path, $this->loadData($path));
      }
    }

    $name_linter = new ArcanistFilenameLinter();
    $linters[] = $name_linter;
    foreach ($paths as $path) {
      $name_linter->addPath($path);
    }

    $xhpast_linter = new ArcanistXHPASTLinter();
    $linters[] = $xhpast_linter;
    foreach ($paths as $path) {
      if (preg_match('/\.php$/', $path)) {
        $xhpast_linter->addPath($path);
        $xhpast_linter->addData($path, $this->loadData($path));
      }
    }

    $linters = array_merge($linters, $this->buildLicenseLinters($paths));
    $linters = array_merge($linters, $this->buildPythonLinters($paths));
    $linters = array_merge($linters, $this->buildJSLinters($paths));

    return $linters;
  }

  public function buildLicenseLinters($paths) {
    $license_linter = new ArcanistApacheLicenseLinter();

    $linters = array();
    $linters[] = $license_linter;
    foreach ($paths as $path) {
      if (preg_match('/\.(php|cpp|hpp|l|y)$/', $path)) {
        if (!preg_match('@^externals/@', $path)) {
          $license_linter->addPath($path);
          $license_linter->addData($path, $this->loadData($path));
        }
      }
    }
    return $linters;
  }

  public function buildPythonLinters($paths) {
    $pyflakes_linter = new ArcanistPyFlakesLinter();
    $pep8_linter = new ArcanistPEP8Linter();

    $linters = array();
    $linters[] = $pyflakes_linter;
    $linters[] = $pep8_linter;
    foreach ($paths as $path) {
      if (preg_match('/\.py$/', $path)) {
        $pyflakes_linter->addPath($path);
        $pyflakes_linter->addData($path, $this->loadData($path));
        $pep8_linter->addPath($path);
        $pep8_linter->addData($path, $this->loadData($path));
      }
    }
    return $linters;
  }

  public function buildJSLinters($paths) {
    $js_linter = new ArcanistJSHintLinter();

    $linters = array();
    $linters[] = $js_linter;
      foreach ($paths as $path) {
        if (preg_match('/\.js$/', $path)) {
          $js_linter->addPath($path);
          $js_linter->addData($path, $this->loadData($path));
        }
      }
    return $linters;
  }

}
