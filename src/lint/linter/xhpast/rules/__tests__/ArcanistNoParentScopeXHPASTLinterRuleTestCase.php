<?php

final class ArcanistNoParentScopeXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/no-parent-scope/');
  }

}
