<?php

final class ArcanistXHPASTLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/xhpast/');
  }

}
