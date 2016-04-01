<?php

final class ArcanistInterfaceAbstractMethodXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/interface-abstract-method/');
  }

}
