<?php

final class ArcanistAbstractPrivateMethodXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
        dirname(__FILE__).'/abstract-private-method/');
  }

}
