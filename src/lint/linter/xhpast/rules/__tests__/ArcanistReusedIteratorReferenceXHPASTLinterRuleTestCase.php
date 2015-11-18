<?php

final class ArcanistReusedIteratorReferenceXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/reused-iterator-reference/');
  }

}
