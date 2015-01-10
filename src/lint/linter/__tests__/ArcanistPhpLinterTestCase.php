<?php

final class ArcanistPhpLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/php/');
  }

}
