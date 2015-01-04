<?php

final class ArcanistMergeConflictLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/mergeconflict/');
  }

}
