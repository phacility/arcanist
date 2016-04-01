<?php

final class ArcanistUnnecessaryFinalModifierXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unnecessary-final-modifier/');
  }

}
