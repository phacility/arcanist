<?php

/**
 * Test cases for @{class:ArcanistTextLinter}.
 *
 * @group testcase
 */
final class ArcanistTextLinterTestCase extends ArcanistLinterTestCase {

  public function testTextLint() {
    $linter = new ArcanistTextLinter();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/text/',
      $linter,
      $working_copy);
  }

}
