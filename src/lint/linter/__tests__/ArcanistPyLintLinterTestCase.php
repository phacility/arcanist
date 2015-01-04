<?php

final class ArcanistPyLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPyLintLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/pylint/');
  }

}
