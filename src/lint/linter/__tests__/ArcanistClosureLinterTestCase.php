<?php

final class ArcanistClosureLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testClosureLinter() {
    $linter = new ArcanistClosureLinter();
    $linter->setFlags(array('--additional_extensions=lint-test'));

    $this->executeTestsInDirectory(
      dirname(__FILE__).'/gjslint/',
      $linter);
  }

}
