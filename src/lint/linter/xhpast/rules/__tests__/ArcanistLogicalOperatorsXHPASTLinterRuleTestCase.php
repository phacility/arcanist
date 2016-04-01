<?php

final class ArcanistLogicalOperatorsXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/logical-operators/');
  }

}
