<?php

final class ArcanistImplicitFallthroughXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/implicit-fallthrough/');
  }

}
