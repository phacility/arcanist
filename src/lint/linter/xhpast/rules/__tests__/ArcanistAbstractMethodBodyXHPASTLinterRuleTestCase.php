<?php

final class ArcanistAbstractMethodBodyXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/abstract-method-body/');
  }

}
