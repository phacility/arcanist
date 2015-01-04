<?php

final class ArcanistPyFlakesLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPyflakesLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/pyflakes/');
  }

}
