<?php

final class ArcanistReusedAsIteratorXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/reused-as-iterator/');
  }

}
