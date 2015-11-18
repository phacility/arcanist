<?php

final class ArcanistDeclarationParenthesesXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/declaration-parentheses/');
  }

}
