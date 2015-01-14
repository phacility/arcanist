<?php

final class ArcanistChmodLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/chmod/');
  }

}
