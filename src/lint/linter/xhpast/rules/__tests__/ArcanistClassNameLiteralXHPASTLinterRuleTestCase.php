<?php

final class ArcanistClassNameLiteralXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/class-name-literal/');
  }

}
