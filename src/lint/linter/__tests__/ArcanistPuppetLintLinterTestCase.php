<?php

final class ArcanistPuppetLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPuppetLintLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/puppet-lint/');
  }

}
