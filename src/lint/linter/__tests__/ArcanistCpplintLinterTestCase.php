<?php

final class ArcanistCpplintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/cpplint/');
  }

}
