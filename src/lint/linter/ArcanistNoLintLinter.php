<?php

/**
 * Stops other linters from running on code marked with a nolint annotation.
 */
final class ArcanistNoLintLinter extends ArcanistLinter {

  public function getInfoName() {
    return pht('Lint Disabler');
  }

  public function getInfoDescription() {
    return pht(
      'Allows you to disable all lint messages for a file by putting "%s" in '.
      'the file body.',
      '@'.'nolint');
  }

  public function getLinterName() {
    return 'NOLINT';
  }

  public function getLinterPriority() {
    return 0.25;
  }

  public function getLinterConfigurationName() {
    return 'nolint';
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function lintPath($path) {
    $data = $this->getData($path);
    if (preg_match('/@'.'nolint/', $data)) {
      $this->stopAllLinters();
    }
  }

}
