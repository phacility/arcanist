<?php

final class ArcanistUselessOverridingMethodXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/useless-overriding-method/');
  }

}
