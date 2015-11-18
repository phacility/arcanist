<?php

final class ArcanistUnsafeDynamicStringXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/unsafe-dynamic-string/');
  }

}
