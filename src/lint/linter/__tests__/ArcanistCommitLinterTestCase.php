<?php

final class ArcanistCommitLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    return $this->executeTestsInDirectory(dirname(__FILE__).'/commit/');
  }

}
