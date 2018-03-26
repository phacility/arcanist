<?php

/**
 * Test for @{class:ArcanistGoTestResultParser}.
 */
final class ArcanistGoTestResultParserTestCase extends PhutilTestCase {

  public function testSingleTestCaseSuccessful() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.single-test-case-successful');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('subpackage_test.go', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage::TestFoo',
      $parsed_results[0]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testSingleTestCaseFailure() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.single-test-case-failure');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('subpackage_test.go', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_FAIL,
      $parsed_results[0]->getResult());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_PASS,
      $parsed_results[1]->getResult());
  }

  public function testMultipleTestCasesSuccessful() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.multiple-test-cases-successful');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(3, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage1::TestFoo1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::Test::package::subpackage2::TestFoo2',
      $parsed_results[2]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testMultipleTestCasesFailure() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.multiple-test-cases-failure');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(3, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage1::TestFoo1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::Test::package::subpackage2::TestFoo2',
      $parsed_results[2]->getName());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_PASS,
      $parsed_results[0]->getResult());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_FAIL,
      $parsed_results[2]->getResult());
  }

  public function testNonVerboseOutput() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.nonverbose');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      'Go::TestCase::package::subpackage1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::TestCase::package::subpackage2',
      $parsed_results[1]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testNonVerboseOutputV110() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.nonverbose-go1.10');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      'Go::TestCase::package::subpackage1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::TestCase::package::subpackage2',
      $parsed_results[1]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testSingleTestCaseSuccessfulGo14() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.single-test-case-successful-go1.4');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('subpackage_test.go', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage::TestFoo',
      $parsed_results[0]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testSingleTestCaseFailureGo14() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.single-test-case-failure-go1.4');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('subpackage_test.go', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_FAIL,
      $parsed_results[0]->getResult());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_PASS,
      $parsed_results[1]->getResult());
  }

  public function testNonVerboseOutputGo14() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.nonverbose-go1.4');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(2, count($parsed_results));
    $this->assertEqual(
      'Go::TestCase::package::subpackage1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::TestCase::package::subpackage2',
      $parsed_results[1]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }

  public function testMultipleTestCasesSuccessfulGo14() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.multiple-test-cases-successful-go1.4');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(3, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage1::TestFoo1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::Test::package::subpackage2::TestFoo2',
      $parsed_results[2]->getName());
    foreach ($parsed_results as $result) {
      $this->assertEqual(
        ArcanistUnitTestResult::RESULT_PASS,
        $result->getResult());
    }
  }


  public function testMultipleTestCasesFailureGo14() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/go.multiple-test-cases-failure-go1.4');

    $parsed_results = id(new ArcanistGoTestResultParser())
      ->parseTestResults('package', $stubbed_results);

    $this->assertEqual(3, count($parsed_results));
    $this->assertEqual(
      'Go::Test::package::subpackage1::TestFoo1',
      $parsed_results[0]->getName());
    $this->assertEqual(
      'Go::Test::package::subpackage2::TestFoo2',
      $parsed_results[2]->getName());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_PASS,
      $parsed_results[0]->getResult());
    $this->assertEqual(
      ArcanistUnitTestResult::RESULT_FAIL,
      $parsed_results[2]->getResult());
  }
}
