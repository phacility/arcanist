<?php

final class ArcanistStaticcheckLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/staticcheck/');
  }

}
