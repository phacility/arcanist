<?php

final class ArcanistConstructorParenthesesXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/constructor-parentheses/');
  }

}
