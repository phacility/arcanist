<?php

/**
 * Lint engine for use in constructing test cases. See
 * @{class:ArcanistLinterTestCase}.
 */
final class ArcanistUnitTestableLintEngine extends ArcanistLintEngine {

  protected $linters = array();

  public function addLinter($linter) {
    $this->linters[] = $linter;
    return $this;
  }

  public function addFileData($path, $data) {
    $this->fileData[$path] = $data;
    return $this;
  }

  public function pathExists($path) {
    if (idx($this->fileData, $path) !== null) {
      return true;
    }
    return parent::pathExists($path);
  }

  public function buildLinters() {
    return $this->linters;
  }

}
