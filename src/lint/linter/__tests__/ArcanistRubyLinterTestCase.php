<?php

/**
 * Test cases for @{class:ArcanistRubyLinter}.
 *
 * @group testcase
 */
final class ArcanistRubyLinterTestCase extends ArcanistLinterTestCase {

  public function testRubyLint() {
    $linter = new ArcanistRubyLinter();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/ruby/',
      $linter,
      $working_copy);
  }

}

