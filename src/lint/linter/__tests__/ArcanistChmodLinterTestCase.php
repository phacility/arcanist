<?php

final class ArcanistChmodLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/chmod/');
  }

}
