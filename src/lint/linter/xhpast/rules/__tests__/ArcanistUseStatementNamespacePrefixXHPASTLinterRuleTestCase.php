<?php

final class ArcanistUseStatementNamespacePrefixXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/use-statement-namespace-prefix/');
  }

}
