<?php

final class ArcanistCpplintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/cpplint/');
  }

}
