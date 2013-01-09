<?php
/**
 * Test cases for @{class:ArcanistCpplintLinter}.
 *
 * @group testcase
 */
final class ArcanistCpplintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCpplintLint() {
    $linter = new ArcanistCpplintLinter();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/cpp/',
      $linter,
      $working_copy);
  }

}
