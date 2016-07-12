<?php

final class ArcanistClassMustBeDeclaredAbstractXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/class-must-be-declared-abstract/');
  }

}
