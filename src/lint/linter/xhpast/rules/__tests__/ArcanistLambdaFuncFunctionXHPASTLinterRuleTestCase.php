<?php

final class ArcanistLambdaFuncFunctionXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/__lambda_func-function/');
  }

}
