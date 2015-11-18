<?php

final class ArcanistCallTimePassByReferenceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/call-time-pass-by-reference/');
  }

}
