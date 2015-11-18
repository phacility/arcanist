<?php

final class ArcanistLanguageConstructParenthesesXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/language-construct-parentheses/');
  }

}
