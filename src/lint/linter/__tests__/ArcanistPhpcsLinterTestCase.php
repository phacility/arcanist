<?php

final class ArcanistPhpcsLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/phpcs/');
  }

}
