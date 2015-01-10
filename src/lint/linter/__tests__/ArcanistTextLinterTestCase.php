<?php

final class ArcanistTextLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/text/');
  }

}
