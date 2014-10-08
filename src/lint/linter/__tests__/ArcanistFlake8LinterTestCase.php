<?php

final class ArcanistFlake8LinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testFlake8Lint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/flake8/',
      new ArcanistFlake8Linter());
  }

}
