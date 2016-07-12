<?php

final class ArcanistDuplicateKeysInArrayXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/duplicate-keys-in-array/');
  }

}
