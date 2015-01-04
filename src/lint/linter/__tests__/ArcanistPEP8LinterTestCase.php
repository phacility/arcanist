<?php

final class ArcanistPEP8LinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testPEP8Linter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/pep8/');
  }

}
