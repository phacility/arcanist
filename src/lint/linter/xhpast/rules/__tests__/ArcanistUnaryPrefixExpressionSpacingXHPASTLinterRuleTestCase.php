<?php

final class ArcanistUnaryPrefixExpressionSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unary-prefix-expression-spacing/');
  }

}
