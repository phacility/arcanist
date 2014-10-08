<?php

/**
 * Checks files for unresolved merge conflicts.
 */
final class ArcanistMergeConflictLinter extends ArcanistLinter {

  const LINT_MERGECONFLICT = 1;

  public function getInfoName() {
    return pht('Merge Conflicts');
  }

  public function getInfoDescription() {
    return pht(
      'Raises errors on unresolved merge conflicts in source files, to catch '.
      'mistakes where a conflicted file is accidentally marked as resolved.');
  }

  public function getLinterName() {
    return 'MERGECONFLICT';
  }

  public function getLinterConfigurationName() {
    return 'merge-conflict';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_MERGECONFLICT => pht('Unresolved merge conflict'),
    );
  }

  public function lintPath($path) {
    $lines = phutil_split_lines($this->getData($path), false);

    foreach ($lines as $lineno => $line) {
      // An unresolved merge conflict will contain a series of seven
      // '<', '=', or '>'.
      if (preg_match('/^(>{7}|<{7}|={7})$/', $line)) {
        $this->raiseLintAtLine(
          $lineno + 1,
          1,
          self::LINT_MERGECONFLICT,
          pht('This syntax indicates there is an unresolved merge conflict.'));
      }
    }
  }

}
