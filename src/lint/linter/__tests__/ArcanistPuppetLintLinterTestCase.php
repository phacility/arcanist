<?php

final class ArcanistPuppetLintLinterTestCase
  extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/puppet-lint/');
  }

}
