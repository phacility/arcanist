<?php

final class ArcanistHLintLinterTestCase extends ArcanistArcanistLinterTestCase {
  public function testHlintLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/hlint/',
      new ArcanistHLintLinter());
  }
}
