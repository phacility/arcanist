<?php

final class ArcanistUndeclaredVariableXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/undeclared-variable/');
  }

}
