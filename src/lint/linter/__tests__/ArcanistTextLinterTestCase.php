<?php

/**
 * Test cases for @{class:ArcanistTextLinter}.
 *
 * @group testcase
 */
final class ArcanistTextLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testTextLint() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/text/',
      new ArcanistTextLinter());
  }

}
