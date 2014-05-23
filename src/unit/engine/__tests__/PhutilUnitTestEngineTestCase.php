<?php

/**
 * Very meta test for @{class:PhutilUnitTestEngine}.
 *
 * @group testcase
 */
final class PhutilUnitTestEngineTestCase extends ArcanistTestCase {

  static $allTestsCounter = 0;
  static $oneTestCounter = 0;
  static $distinctWillRunTests = array();
  static $distinctDidRunTests = array();

  protected function willRunTests() {
    self::$allTestsCounter++;
  }

  protected function didRunTests() {
    $this->assertEqual(
      1,
      self::$allTestsCounter,
      'Expect willRunTests() has been called once.');

    self::$allTestsCounter--;

    $actual_test_count = 4;

    $this->assertEqual(
      $actual_test_count,
      count(self::$distinctWillRunTests),
      'Expect willRunOneTest() was called once for each test.');
    $this->assertEqual(
      $actual_test_count,
      count(self::$distinctDidRunTests),
      'Expect didRunOneTest() was called once for each test.');
    $this->assertEqual(
      self::$distinctWillRunTests,
      self::$distinctDidRunTests,
      'Expect same tests had pre- and post-run callbacks invoked.');
  }

  public function __destruct() {
    if (self::$allTestsCounter !== 0) {
      throw new Exception(
        'didRunTests() was not called correctly after tests completed!');
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
      'Expect willRunOneTest depth to be one.');

    self::$distinctDidRunTests[$test] = true;
    self::$oneTestCounter--;
  }

  public function testPass() {
    $this->assertEqual(1, 1, 'This test is expected to pass.');
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
        $this->assertFailure('These tests should either fail or skip.');
      }
    }
    $this->assertEqual(1, $failed, 'One test was expected to fail.');
    $this->assertEqual(1, $skipped, 'One test was expected to skip.');
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
      throw new Exception('This is a negative test case!');
    }
  }

}
