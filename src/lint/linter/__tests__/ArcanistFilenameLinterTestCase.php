<?php

final class ArcanistFilenameLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/filename/');
  }

}
