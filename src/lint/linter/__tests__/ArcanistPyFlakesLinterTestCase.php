<?php

final class ArcanistPyFlakesLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPyflakesLinter() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/pyflakes/',
      new ArcanistPyFlakesLinter());
  }

}
