<?php

final class ArcanistPHPCloseTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/php-close-tag/');
  }

}
