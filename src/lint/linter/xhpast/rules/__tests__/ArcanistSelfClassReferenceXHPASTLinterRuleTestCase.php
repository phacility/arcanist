<?php

final class ArcanistSelfClassReferenceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/self-class-reference/');
  }

}
