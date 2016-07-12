<?php

final class ArcanistInterfaceMethodBodyXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/interface-method-body/');
  }

}
