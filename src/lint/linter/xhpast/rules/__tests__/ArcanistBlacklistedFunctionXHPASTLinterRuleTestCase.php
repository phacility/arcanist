<?php

final class ArcanistBlacklistedFunctionXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/blacklisted-function/');
  }

}
