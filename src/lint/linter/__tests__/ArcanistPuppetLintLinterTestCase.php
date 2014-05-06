<?php

final class ArcanistPuppetLintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testPuppetLintLinter() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/puppet-lint/',
      new ArcanistPuppetLintLinter());
  }

}
