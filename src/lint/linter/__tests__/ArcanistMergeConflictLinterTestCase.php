<?php

final class ArcanistMergeConflictLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/mergeconflict/');
  }

}
