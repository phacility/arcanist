<?php

/**
 * Checks files for unresolved merge conflicts.
 *
 * @group linter
 */
final class ArcanistMergeConflictLinter extends ArcanistLinter {
  const LINT_MERGECONFLICT = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function lintPath($path) {
    $lines = phutil_split_lines($this->getData($path), false);

    foreach ($lines as $lineno => $line) {
      // An unresolved merge conflict will contain a series of seven
      // '<', '=', or '>'.
      if (preg_match('/^(>{7}|<{7}|={7})$/', $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          0,
          self::LINT_MERGECONFLICT,
          "This syntax indicates there is an unresolved merge conflict.");
      }
    }
  }

  public function getLinterName() {
    return "MERGECONFLICT";
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_MERGECONFLICT => ArcanistLintSeverity::SEVERITY_ERROR
    );
  }

  public function getLintNameMap() {
    return array(
      self::LINT_MERGECONFLICT => "Unresolved merge conflict"
    );
  }
}
