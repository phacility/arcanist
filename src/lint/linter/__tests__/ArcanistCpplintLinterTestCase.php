<?php

final class ArcanistCpplintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCpplintLint() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/cpplint/');
  }

}
