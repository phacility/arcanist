<?php

/**
 * Test cases for @{class:ArcanistRubyLinter}.
 *
 * @group testcase
 */
final class ArcanistRubyLinterTestCase extends ArcanistArcanistLinterTestCase {

  public function testRubyLint() {
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/ruby/',
      new ArcanistRubyLinter());
  }

}
