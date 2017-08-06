<?php

final class ArcanistComposerLinterTestCase
  extends PhutilTestCase {

  public function testLinter() {
    $this->assertEqual(null, $this->getFirstLintMessage('new-hash-correct'));
    $this->assertEqual(null, $this->getFirstLintMessage('old-hash-correct'));
    $this->assertEqual(
      'Lock file out-of-date',
      $this->getFirstLintMessage('new-hash-wrong'));
    $this->assertEqual(
      'Lock file out-of-date',
      $this->getFirstLintMessage('old-hash-wrong'));
  }

  private function getFirstLintMessage($composer_path) {
    $composer_path = dirname(__FILE__).'/composer/'.
      $composer_path.'/composer.json';
    $engine = new ArcanistUnitTestableLintEngine();
    $linter = new ArcanistComposerLinter();
    $linter->setEngine($engine);
    $linter->addPath($composer_path);
    $linter->lintPath($composer_path);
    $messages = $linter->getLintMessages();
    if (count($messages) == 0) {
        return null;
    }
    return reset($messages)->getName();
  }
}
