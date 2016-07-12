<?php

final class ArcanistCoffeeLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/coffeelint/');
  }

}
