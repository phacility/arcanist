<?php

final class ArcanistCurlyBraceArrayIndexXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/curly-brace-array-index/');
  }

}
