<?php

final class ArcanistGeneratedLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/generated/');
  }

}
