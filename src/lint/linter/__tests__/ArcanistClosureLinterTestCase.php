<?php

final class ArcanistClosureLinterTestCase
  extends ArcanistExternalLinterTestCase {

  protected function getLinter() {
    $linter = new ArcanistClosureLinter();
    $linter->setFlags(array('--additional_extensions=lint-test'));
    return $linter;
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/gjslint/');
  }

}
