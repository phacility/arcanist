<?php

/**
 * Very meta test for @{class:PhutilUnitTestEngine}.
 */
final class PhutilUnitTestEngineTestCase extends ArcanistTestCase {

  private static $allTestsCounter = 0;
  private static $oneTestCounter = 0;
  private static $distinctWillRunTests = array();
  private static $distinctDidRunTests = array();

  protected function willRunTests() {
    self::$allTestsCounter++;
  }

  protected function didRunTests() {
    $this->assertEqual(
      1,
      self::$allTestsCounter,
      pht(
        'Expect %s has been called once.',
        'willRunTests()'));

    self::$allTestsCounter--;

    $actual_test_count = 4;

    $this->assertEqual(
      $actual_test_count,
      count(self::$distinctWillRunTests),
      pht(
        'Expect %s was called once for each test.',
        'willRunOneTest()'));
    $this->assertEqual(
      $actual_test_count,
      count(self::$distinctDidRunTests),
      pht(
        'Expect %s was called once for each test.',
        'didRunOneTest()'));
    $this->assertEqual(
      self::$distinctWillRunTests,
      self::$distinctDidRunTests,
      pht('Expect same tests had pre-run and post-run callbacks invoked.'));
  }

  public function __destruct() {
    if (self::$allTestsCounter !== 0) {
      throw new Exception(
        pht(
          '%s was not called correctly after tests completed!',
          'didRunTests()'));
    }
  }

  protected function willRunOneTest($test) {
    self::$distinctWillRunTests[$test] = true;
    self::$oneTestCounter++;
  }

  protected function didRunOneTest($test) {
    $this->assertEqual(
      1,
      self::$oneTestCounter,
      pht('Expect %s depth to be one.', 'willRunOneTest()'));

    self::$distinctDidRunTests[$test] = true;
    self::$oneTestCounter--;
  }

  public function testPass() {
    $this->assertEqual(1, 1, pht('This test is expected to pass.'));
  }

  public function testFailSkip() {
    $failed = 0;
    $skipped = 0;
    $test_case = new ArcanistPhutilTestCaseTestCase();
    foreach ($test_case->run() as $result) {
      if ($result->getResult() == ArcanistUnitTestResult::RESULT_FAIL) {
        $failed++;
      } else if ($result->getResult() == ArcanistUnitTestResult::RESULT_SKIP) {
        $skipped++;
      } else {
        $this->assertFailure(pht('These tests should either fail or skip.'));
      }
    }
    $this->assertEqual(1, $failed, pht('One test was expected to fail.'));
    $this->assertEqual(1, $skipped, pht('One test was expected to skip.'));
  }

  public function testTryTestCases() {
    $this->tryTestCases(
      array(
        true,
        false,
      ),
      array(
        true,
        false,
      ),
      array($this, 'throwIfFalsey'));
  }

  public function testTryTestMap() {
    $this->tryTestCaseMap(
      array(
        1 => true,
        0 => false,
      ),
      array($this, 'throwIfFalsey'));
  }

  protected function throwIfFalsey($input) {
    if (!$input) {
      throw new Exception(pht('This is a negative test case!'));
    }
  }

}
