<?php

final class ArcanistCppcheckLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCppcheckLint() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/cppcheck/');
  }

}
