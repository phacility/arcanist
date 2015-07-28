<?php

/**
 * Very meta test for @{class:PhutilUnitTestEngine}.
 */
final class PhutilUnitTestEngineTestCase extends PhutilTestCase {

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

    $actual_test_count = 5;

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

    $test_case = id(new PhutilTestCaseTestCase())
      ->setWorkingCopy($this->getWorkingCopy());

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

  public function testGetTestPaths() {
    $tests = array(
      'empty' => array(
        array(),
        array(),
      ),

      'test file' => array(
        array(__FILE__),
        array(__FILE__),
      ),

      'test directory' => array(
        array(
          dirname(__FILE__),
        ),
        array(
          // This is odd, but harmless.
          dirname(dirname(__FILE__)).'/__tests__/__tests__/',

          dirname(dirname(__FILE__)).'/__tests__/',
          dirname(dirname(dirname(__FILE__))).'/__tests__/',
          phutil_get_library_root('arcanist').'/__tests__/',
        ),
      ),
      'normal directory' => array(
        array(
          dirname(dirname(__FILE__)),
        ),
        array(
          dirname(dirname(__FILE__)).'/__tests__/',
          dirname(dirname(dirname(__FILE__))).'/__tests__/',
          phutil_get_library_root('arcanist').'/__tests__/',
        ),
      ),
      'library root' => array(
        array(phutil_get_library_root('arcanist')),
        array(phutil_get_library_root('arcanist').'/__tests__/'),
      ),
    );

    $test_engine = id(new PhutilUnitTestEngine())
      ->setWorkingCopy($this->getWorkingCopy());

    $library = phutil_get_current_library_name();
    $library_root = phutil_get_library_root($library);

    foreach ($tests as $name => $test) {
      list($paths, $test_paths) = $test;
      $expected = array();

      foreach ($test_paths as $path) {
        $expected[] = array(
          'library' => $library,
          'path' => Filesystem::readablePath($path, $library_root),
        );
      }

      $test_engine->setPaths($paths);
      $this->assertEqual(
        $expected,
        array_values($test_engine->getTestPaths()),
        pht('Test paths for: "%s"', $name));
    }
  }

}
