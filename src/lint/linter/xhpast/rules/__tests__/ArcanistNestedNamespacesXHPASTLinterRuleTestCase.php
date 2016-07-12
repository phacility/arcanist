<?php

final class ArcanistNestedNamespacesXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/nested-namespaces/');
  }

}
