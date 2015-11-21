<?php

final class ArcanistTautologicalExpressionXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/tautological-expression/');
  }

}
