<?php

final class ArcanistCoffeeLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCoffeeLintLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/coffeelint/');
  }

}
