<?php

/**
 * This linter just stops the lint process when a file is marked as generated
 * code.
 */
class ArcanistGeneratedLinter extends ArcanistLinter {

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
    $data = $this->getData($path);
    
    if (preg_match('/@generated/', $data)) {
      $this->stopAllLinters();
    }
  }

}
