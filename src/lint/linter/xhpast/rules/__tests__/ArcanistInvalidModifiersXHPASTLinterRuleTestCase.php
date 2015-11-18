<?php

final class ArcanistInvalidModifiersXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/invalid-modifiers/');
  }

}
