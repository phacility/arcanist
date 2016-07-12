<?php

final class ArcanistClassExtendsObjectXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/class-extends-object/');
  }

}
