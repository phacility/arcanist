<?php

final class ArcanistUnexpectedReturnValueXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unexpected-return-value/');
  }

}
