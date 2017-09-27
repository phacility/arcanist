<?php

/**
 * Test for @{class:ArcanistXUnitTestResultParser}.
 */
final class XUnitTestResultParserTestCase extends PhutilTestCase {

  public function testAcceptsNoTestsInput() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/xunit.no-tests');
    $parsed_results = id(new ArcanistXUnitTestResultParser())
      ->parseTestResults($stubbed_results);

    $this->assertEqual(0, count($parsed_results));
  }

  public function testAcceptsSimpleInput() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/xunit.simple');
    $parsed_results = id(new ArcanistXUnitTestResultParser())
      ->parseTestResults($stubbed_results);

    $this->assertEqual(3, count($parsed_results));
  }

  public function testEmptyInputFailure() {
    try {
      $parsed_results = id(new ArcanistXUnitTestResultParser())
        ->parseTestResults('');

      $this->assertFailure(pht('Should throw on empty input'));
    } catch (Exception $e) {
      // OK
    }

    $this->assertTrue(true);
  }

  public function testInvalidXmlInputFailure() {
    $stubbed_results = Filesystem::readFile(
      dirname(__FILE__).'/testresults/xunit.invalid-xml');
    try {
      $parsed_results = id(new ArcanistXUnitTestResultParser())
        ->parseTestResults($stubbed_results);

      $this->assertFailure(pht('Should throw on non-xml input'));
    } catch (Exception $e) {
      // OK
    }

    $this->assertTrue(true);
  }

}
