<?php

final class ArcanistThisReassignmentXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/this-reassignment/');
  }

}
