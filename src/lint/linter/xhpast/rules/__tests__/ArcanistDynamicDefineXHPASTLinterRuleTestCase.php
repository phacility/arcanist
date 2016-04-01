<?php

final class ArcanistDynamicDefineXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/dynamic-define/');
  }

}
