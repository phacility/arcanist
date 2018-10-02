<?php

final class ArcanistXHPASTLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->assertExecutable('xhpast');

    $this->executeTestsInDirectory(dirname(__FILE__).'/xhpast/');
  }

}
