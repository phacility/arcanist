<?php

final class ArcanistParentMemberReferenceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/parent-member-references/');
  }

}
