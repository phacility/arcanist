<?php

final class ArcanistBinaryNumericScalarCasingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/binary-numeric-scalar-casing/');
  }

}
