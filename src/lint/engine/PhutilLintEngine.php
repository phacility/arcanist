<?php

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

    return $linters;
  }

  private function getXHPASTSeverityMap() {
    $error = ArcanistLintSeverity::SEVERITY_ERROR;
    $warning = ArcanistLintSeverity::SEVERITY_WARNING;
    $advice = ArcanistLintSeverity::SEVERITY_ADVICE;

    return array(
      ArcanistXHPASTLinter::LINT_PHP_53_FEATURES          => $error,
      ArcanistXHPASTLinter::LINT_PHP_54_FEATURES          => $error,
      ArcanistXHPASTLinter::LINT_PHT_WITH_DYNAMIC_STRING  => $error,
      ArcanistXHPASTLinter::LINT_COMMENT_SPACING          => $error,
      ArcanistXHPASTLinter::LINT_RAGGED_CLASSTREE_EDGE    => $warning,
      ArcanistXHPASTLinter::LINT_TODO_COMMENT             => $advice,
      ArcanistXHPASTLinter::LINT_ARRAY_COMBINE            => $warning,
    );
  }
}
