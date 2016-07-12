<?php

final class ArcanistNewlineAfterOpenTagXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/newline-after-open-tag/');
  }

}
