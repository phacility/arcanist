<?php

/**
 * Stops other linters from running on generated code.
 */
final class ArcanistGeneratedLinter extends ArcanistLinter {

  public function getInfoName() {
    return pht('Generated Code');
  }

  public function getInfoDescription() {
    return pht(
      'Disables lint for files that are marked as "%s", '.
      'indicating that they contain generated code.',
      '@'.'generated');
  }

  public function getLinterName() {
    return 'GEN';
  }

  public function getLinterPriority() {
    return 0.25;
  }

  public function getLinterConfigurationName() {
    return 'generated';
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  protected function lintPath(ArcanistWorkingCopyPath $path) {
    if (preg_match('/@'.'generated/', $path->getData())) {
      $this->stopAllLinters();
    }
  }

}
