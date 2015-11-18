<?php

final class ArcanistPHPCompatibilityXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/php-compatibility/');
  }

}
