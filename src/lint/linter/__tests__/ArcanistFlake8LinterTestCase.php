<?php

final class ArcanistFlake8LinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/flake8/');
  }

}
