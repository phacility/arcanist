<?php

final class ArcanistRuboCopLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/rubocop/');
  }

}
