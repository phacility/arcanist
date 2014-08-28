<?php

final class ArcanistPhpLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testPHPLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/php/',
      new ArcanistPhpLinter());
  }

}
