<?php

final class ArcanistXHPASTLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/xhpast/');
  }

}
