<?php

/**
 * Test for @{class:PhutilUnitTestEngineTestCase}.
 */
final class PhutilTestCaseTestCase extends PhutilTestCase {

  public function testFail() {
    $this->assertFailure(pht('This test is expected to fail.'));
  }

  public function testSkip() {
    $this->assertSkipped(pht('This test is expected to skip.'));
  }

}
