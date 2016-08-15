<?php

final class ArcanistGoVetLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/govet/');
  }

}
