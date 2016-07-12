<?php

final class ArcanistImplicitConstructorXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/implicit-constructor/');
  }

}
