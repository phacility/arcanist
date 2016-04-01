<?php

final class ArcanistPregQuoteMisuseXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/preg_quote-misuse/');
  }

}
