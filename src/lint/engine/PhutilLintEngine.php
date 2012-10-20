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
 * Lint engine which enforces libphutil rules.
 *
 * TODO: Deal with PhabricatorLintEngine extending this and then finalize it.
 *
 * @group linter
 */
class PhutilLintEngine extends ArcanistLintEngine {

  public function buildLinters() {
    $linters = array();

    $paths = $this->getPaths();

    $linters[] = id(new ArcanistPhutilLibraryLinter())->setPaths($paths);

    // Remaining linters operate on file contents and ignore removed files.
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

    $linters[] = id(new ArcanistFilenameLinter())->setPaths($paths);

    // Skip directories and lint only regular files in remaining linters.
    foreach ($paths as $key => $path) {
      if ($this->getCommitHookMode()) {
        continue;
      }
      if (!is_file($this->getFilePathOnDisk($path))) {
        unset($paths[$key]);
      }
    }

    $text_paths = preg_grep('/\.(php|css|js|hpp|cpp|l|y)$/', $paths);
    $linters[] = id(new ArcanistGeneratedLinter())->setPaths($text_paths);
    $linters[] = id(new ArcanistNoLintLinter())->setPaths($text_paths);
    $linters[] = id(new ArcanistTextLinter())->setPaths($text_paths);
    $linters[] = id(new ArcanistSpellingLinter())->setPaths($text_paths);

    $linters[] = id(new ArcanistXHPASTLinter())
      ->setCustomSeverityMap($this->getXHPASTSeverityMap())
      ->setPaths(preg_grep('/\.php$/', $paths));

    $linters[] = id(new ArcanistApacheLicenseLinter())
      ->setPaths(preg_grep('/\.(php|cpp|hpp|l|y)$/', $paths));

    return $linters;
  }

  private function getXHPASTSeverityMap() {
    $error = ArcanistLintSeverity::SEVERITY_ERROR;
    $warning = ArcanistLintSeverity::SEVERITY_WARNING;

    return array(
      ArcanistXHPASTLinter::LINT_PHP_53_FEATURES          => $error,
      ArcanistXHPASTLinter::LINT_PHP_54_FEATURES          => $error,
      ArcanistXHPASTLinter::LINT_PHT_WITH_DYNAMIC_STRING  => $error,
      ArcanistXHPASTLinter::LINT_COMMENT_SPACING          => $error,

      ArcanistXHPASTLinter::LINT_RAGGED_CLASSTREE_EDGE    => $warning,
    );
  }
}
