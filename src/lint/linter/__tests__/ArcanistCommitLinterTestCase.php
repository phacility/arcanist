<?php

final class ArcanistCommitLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/commit/');
  }

}
