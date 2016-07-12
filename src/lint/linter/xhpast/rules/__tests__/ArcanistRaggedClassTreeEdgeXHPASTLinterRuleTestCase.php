<?php

final class ArcanistRaggedClassTreeEdgeXHPASTLinterRuleTestCase
  extends ArcanistXHPASTLinterRuleTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/ragged-classtree-edge/');
  }

}
