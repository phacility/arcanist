<?php
/**
 * Test cases for @{class:ArcanistFlake8Linter}.
 *
 * @group testcase
 */
final class ArcanistFlake8LinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testFlake8Lint() {
    $linter = new ArcanistFlake8Linter();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/python/',
      $linter,
      $working_copy);
  }

}
