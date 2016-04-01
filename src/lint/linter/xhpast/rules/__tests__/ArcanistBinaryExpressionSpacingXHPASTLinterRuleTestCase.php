<?php

final class ArcanistBinaryExpressionSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/binary-expression-spacing/');
  }

}
