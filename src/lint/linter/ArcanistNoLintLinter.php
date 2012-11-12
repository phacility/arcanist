<?php

/**
 * Stops other linters from running on code marked with
 * a nolint annotation.
 *
 * @group linter
 */
final class ArcanistNoLintLinter extends ArcanistLinter {
  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'NOLINT';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function lintPath($path) {
    $data = $this->getData($path);

    if (preg_match('/@'.'nolint/', $data)) {
      $this->stopAllLinters();
    }
  }
}
