<?php

final class ArcanistPyFlakesLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/pyflakes/');
  }

}
