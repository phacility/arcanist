<?php

final class ArcanistFunctionCallShouldBeTypeCastXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/function-call-should-be-type-cast/');
  }

}
