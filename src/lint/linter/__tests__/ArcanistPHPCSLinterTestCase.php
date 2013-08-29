<?php

final class ArcanistPHPCSLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testPHPCSLint() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/phpcs/',
      new ArcanistPhpcsLinter());
  }

}
