<?php

final class ArcanistNamespaceFirstStatementXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/namespace-first-statement/');
  }

}
