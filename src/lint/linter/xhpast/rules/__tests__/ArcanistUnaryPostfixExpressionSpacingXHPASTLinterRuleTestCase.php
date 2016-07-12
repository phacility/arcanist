<?php

final class ArcanistUnaryPostfixExpressionSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unary-postfix-expression-spacing/');
  }

}
