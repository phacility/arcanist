<?php

final class UberShellCheckLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/shellcheck/');
  }

}
