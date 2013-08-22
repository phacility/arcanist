<?php
/**
 * Test cases for @{class:ArcanistCpplintLinter}.
 *
 * @group testcase
 */
final class ArcanistCpplintLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testCpplintLint() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/cpp/',
      new ArcanistCpplintLinter());
  }

}
