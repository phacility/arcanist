<?php

final class ArcanistGeneratedLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/generated/');
  }

}
