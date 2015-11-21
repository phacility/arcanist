<?php

final class ArcanistInstanceofOperatorXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/instanceof-operator/');
  }

}
