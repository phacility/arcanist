<?php

final class ArcanistCommitLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCommitLint() {
    $this->executeTestsInDirectory(
      dirname(__FILE__).'/commit/',
      new ArcanistCommitLinter());
  }

}
