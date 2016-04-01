<?php

final class ArcanistVariableReferenceSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/variable-reference-spacing/');
  }

}
