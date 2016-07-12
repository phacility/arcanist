<?php

final class ArcanistUnnecessarySymbolAliasXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/unnecessary-symbol-alias/');
  }

}
