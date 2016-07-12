<?php

final class ArcanistLowercaseFunctionsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/lowercase-functions/');
  }

}
