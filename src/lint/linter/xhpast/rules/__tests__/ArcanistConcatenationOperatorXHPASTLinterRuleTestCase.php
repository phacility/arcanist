<?php

final class ArcanistConcatenationOperatorXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/concatenation-operator/');
  }

}
