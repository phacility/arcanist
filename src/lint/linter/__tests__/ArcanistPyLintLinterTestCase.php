<?php

final class ArcanistPyLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/pylint/');
  }

}
