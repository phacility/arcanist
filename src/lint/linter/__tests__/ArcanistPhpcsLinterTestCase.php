<?php

final class ArcanistPhpcsLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testPHPCSLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/phpcs/',
      new ArcanistPhpcsLinter());
  }

}
