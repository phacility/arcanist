<?php

final class ArcanistImplicitVisibilityXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/implicit-visibility/');
  }

}
