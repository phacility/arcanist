<?php

final class ArcanistMergeConflictLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testMergeConflictLint() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/mergeconflict/');
  }

}
