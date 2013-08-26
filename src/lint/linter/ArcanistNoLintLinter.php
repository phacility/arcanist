<?php

/**
 * Stops other linters from running on code marked with
 * a nolint annotation.
 *
 * @group linter
 */
final class ArcanistNoLintLinter extends ArcanistLinter {

  public function getLinterName() {
    return 'NOLINT';
  }

  public function getLinterPriority() {
    return 0.25;
  }

  public function getLinterConfigurationName() {
    return 'nolint';
  }

  public function lintPath($path) {
    $data = $this->getData($path);
    if (preg_match('/@'.'nolint/', $data)) {
      $this->stopAllLinters();
    }
  }

}
