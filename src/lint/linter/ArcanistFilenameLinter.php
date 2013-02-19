<?php

/**
 * Stifles creativity in choosing imaginative file names.
 *
 * @group linter
 */
final class ArcanistFilenameLinter extends ArcanistLinter {

  const LINT_BAD_FILENAME = 1;

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'NAME';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
      self::LINT_BAD_FILENAME   => 'Bad Filename',
    );
  }

  public function lintPath($path) {
    if (!preg_match('@^[a-z0-9./\\\\_-]+$@i', $path)) {
      $this->raiseLintAtPath(
        self::LINT_BAD_FILENAME,
        'Name files using only letters, numbers, period, hyphen and '.
        'underscore.');
    }
  }

}
