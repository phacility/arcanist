<?php

final class ArcanistImplodeArgumentOrderXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/implode-argument-order/');
  }

}
