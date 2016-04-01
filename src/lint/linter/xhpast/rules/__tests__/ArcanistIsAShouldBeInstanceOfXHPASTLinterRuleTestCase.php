<?php

final class ArcanistIsAShouldBeInstanceOfXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/is_a-should-be-instanceof/');
  }

}
