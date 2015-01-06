<?php

final class ArcanistClosureLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $linter = new ArcanistClosureLinter();
    $linter->setFlags(array('--additional_extensions=lint-test'));

    $this->executeTestsInDirectory(dirname(__FILE__).'/gjslint/', $linter);
  }

}
