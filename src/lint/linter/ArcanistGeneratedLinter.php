<?php

/**
 * Stops other linters from running on generated code.
 *
 * @group linter
 */
final class ArcanistGeneratedLinter extends ArcanistLinter {

  public function willLintPaths(array $paths) {
    return;
  }

  public function getLinterName() {
    return 'GEN';
  }

  public function getLintSeverityMap() {
    return array();
  }

  public function getLintNameMap() {
    return array(
    );
  }

  public function lintPath($path) {
    if ($this->isBinaryFile($path)) {
      return;
    }

    $data = $this->getData($path);

    if (preg_match('/@'.'generated/', $data)) {
      $this->stopAllLinters();
    }
  }

}
