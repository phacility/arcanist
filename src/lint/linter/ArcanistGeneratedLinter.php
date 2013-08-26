<?php

/**
 * Stops other linters from running on generated code.
 *
 * @group linter
 */
final class ArcanistGeneratedLinter extends ArcanistLinter {

  public function getLinterName() {
    return 'GEN';
  }

  public function getLinterPriority() {
    return 0.25;
  }

  public function getLinterConfigurationName() {
    return 'generated';
  }

  public function lintPath($path) {
    $data = $this->getData($path);
    if (preg_match('/@'.'generated/', $data)) {
      $this->stopAllLinters();
    }
  }

}
