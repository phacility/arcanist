<?php

final class ArcanistCommitLinter extends ArcanistLinter {

  const LINT_NO_COMMIT = 1;

  public function getInfoName() {
    return pht('Commit Linter');
  }

  public function getInfoDescription() {
    return pht('Ensures that specially marked files are not committed.');
  }

  public function getLinterPriority() {
    return 0.5;
  }

  public function getLinterName() {
    return 'COMMIT';
  }

  public function getLinterConfigurationName() {
    return 'commit';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_NO_COMMIT => pht('Explicit %s', '@no'.'commit'),
    );
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function lintPath($path) {
    if ($this->getEngine()->getCommitHookMode()) {
      $this->lintNoCommit($path);
    }
  }

  private function lintNoCommit($path) {
    $data = $this->getData($path);

    $deadly = '@no'.'commit';
    $offset = strpos($data, $deadly);

    if ($offset !== false) {
      $this->raiseLintAtOffset(
        $offset,
        self::LINT_NO_COMMIT,
        pht(
          'This file is explicitly marked as "%s", which blocks commits.',
          $deadly),
        $deadly);
    }
  }

}
