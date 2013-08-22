<?php
/**
 * Test cases for @{class:ArcanistFlake8Linter}.
 *
 * @group testcase
 */
final class ArcanistFlake8LinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testFlake8Lint() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/python/',
      new ArcanistFlake8Linter());
  }

}
