<?php

final class ArcanistFilenameLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/filename/');
  }

}
