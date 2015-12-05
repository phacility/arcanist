<?php

final class ArcanistInvalidOctalNumericScalarXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/invalid-octal-numeric-scalar/');
  }

}
