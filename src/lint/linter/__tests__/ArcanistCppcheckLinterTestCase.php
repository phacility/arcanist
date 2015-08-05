<?php

final class ArcanistCppcheckLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/cppcheck/');
  }

}
