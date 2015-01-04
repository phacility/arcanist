<?php

final class ArcanistCppcheckLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/cppcheck/');
  }

}
