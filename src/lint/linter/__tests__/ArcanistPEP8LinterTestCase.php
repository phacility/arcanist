<?php

final class ArcanistPEP8LinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/pep8/');
  }

}
