<?php

final class ArcanistCallParenthesesXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/call-parentheses/');
  }

}
