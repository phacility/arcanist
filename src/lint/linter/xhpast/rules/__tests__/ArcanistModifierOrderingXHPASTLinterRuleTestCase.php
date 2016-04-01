<?php

final class ArcanistModifierOrderingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/modifier-ordering/');
  }

}
