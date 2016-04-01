<?php

final class ArcanistPaamayimNekudotayimSpacingXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/paamayim-nekudotayim-spacing/');
  }

}
