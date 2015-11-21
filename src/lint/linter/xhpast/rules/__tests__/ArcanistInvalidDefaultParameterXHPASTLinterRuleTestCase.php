<?php

final class ArcanistInvalidDefaultParameterXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/invalid-default-parameter/');
  }

}
