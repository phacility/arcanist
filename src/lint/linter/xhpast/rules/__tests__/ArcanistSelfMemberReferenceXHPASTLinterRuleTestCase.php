<?php

final class ArcanistSelfMemberReferenceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/self-member-reference/');
  }

}
