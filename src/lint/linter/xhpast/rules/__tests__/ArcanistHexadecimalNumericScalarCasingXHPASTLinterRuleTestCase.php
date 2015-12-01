<?php

final class ArcanistHexadecimalNumericScalarCasingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/hexadecimal-numeric-scalar-casing/');
  }

}
