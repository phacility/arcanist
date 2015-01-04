<?php

final class ArcanistCommitLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCommitLint() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/commit/');
  }

}
