<?php

final class ArcanistCSSLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCSSLintLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/csslint/',
      new ArcanistCSSLintLinter());
  }

}
