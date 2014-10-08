<?php

/**
 * Test for @{class:PhutilUnitTestEngineTestCase}.
 */
final class ArcanistPhutilTestCaseTestCase extends ArcanistPhutilTestCase {

  public function testFail() {
    $this->assertFailure('This test is expected to fail.');
  }

  public function testSkip() {
    $this->assertSkipped('This test is expected to skip.');
  }

}
