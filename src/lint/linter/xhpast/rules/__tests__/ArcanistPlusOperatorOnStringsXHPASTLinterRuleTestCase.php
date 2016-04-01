<?php

final class ArcanistPlusOperatorOnStringsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/plus-operator-on-strings/');
  }

}
