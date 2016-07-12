<?php

final class ArcanistDuplicateSwitchCaseXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/duplicate-switch-case/');
  }

}
