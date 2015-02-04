<?php

/**
 * Test for @{class:PhutilUnitTestEngineTestCase}.
 */
final class ArcanistPhutilTestCaseTestCase extends ArcanistPhutilTestCase {

  public function testFail() {
    $this->assertFailure(pht('This test is expected to fail.'));
  }

  public function testSkip() {
    $this->assertSkipped(pht('This test is expected to skip.'));
  }

}
