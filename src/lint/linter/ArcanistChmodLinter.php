<?php

/**
 * Ensures that files are not executable unless they are either binary or
 * contain a shebang.
 */
final class ArcanistChmodLinter extends ArcanistLinter {

  const LINT_INVALID_EXECUTABLE = 1;

  public function getInfoName() {
    return 'Chmod';
  }

  public function getInfoDescription() {
    return pht(
      'Checks the permissions on files and ensures that they are not made to '.
      'be executable unnecessarily. In particular, a file should not be '.
      'executable unless it is either binary or contain a shebang.');
  }

  public function getLinterName() {
    return 'CHMOD';
  }

  public function getLinterConfigurationName() {
    return 'chmod';
  }

  public function shouldLintBinaryFiles() {
    return true;
  }

  public function getLintNameMap() {
    return array(
      self::LINT_INVALID_EXECUTABLE => pht('Invalid Executable'),
    );
  }

  public function getLintSeverityMap() {
    return array(
      self::LINT_INVALID_EXECUTABLE => ArcanistLintSeverity::SEVERITY_WARNING,
    );
  }

  public function lintPath($path) {
    if (is_executable($path)) {
      if ($this->getEngine()->isBinaryFile($path)) {
        // Path is a binary file, which makes it a valid executable.
        return;
      } else if ($this->getShebang($path)) {
        // Path contains a shebang, which makes it a valid executable.
        return;
      } else {
        $this->raiseLintAtPath(
          self::LINT_INVALID_EXECUTABLE,
          pht(
            'Executable files should either be binary or contain a shebang.'));
      }
    }
  }

  /**
   * Returns the path's shebang.
   *
   * @param  string
   * @return string|null
   */
  private function getShebang($path) {
    $line = head(phutil_split_lines($this->getEngine()->loadData($path), true));

    $matches = array();
    if (preg_match('/^#!(.*)$/', $line, $matches)) {
      return $matches[1];
    } else {
      return null;
    }
  }

}
