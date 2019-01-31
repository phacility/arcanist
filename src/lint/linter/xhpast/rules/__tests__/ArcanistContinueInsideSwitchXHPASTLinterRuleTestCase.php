<?php

final class ArcanistContinueInsideSwitchXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/continue-inside-switch/');
  }

}
