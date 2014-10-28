<?php

final class ArcanistSCSSLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testSCSSLintLinter() {
    $linter = new ArcanistSCSSLintLinter();

    $this->executeTestsInDirectory(
      dirname(__FILE__).'/scss-lint/',$linter);
  }

}
