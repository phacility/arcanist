<?php

/**
 * Parser for JUnit, NUnit, etc results format
 */
final class ArcanistXUnitTestResultParser extends Phobject {

  /**
   * Parse test results from provided input and return an array
   * of @{class:ArcanistUnitTestResult}.
   *
   * @param string $test_results String containing test results
   *
   * @return array ArcanistUnitTestResult
   */
  public function parseTestResults($test_results) {
    if (!strlen($test_results)) {
      throw new Exception(
        pht(
          '%s argument to %s must not be empty',
          'test_results',
          'parseTestResults()'));
    }

    // xunit xsd: https://gist.github.com/959290
    $xunit_dom = new DOMDocument();
    $load_success = @$xunit_dom->loadXML($test_results);

    if (!$load_success) {
      $input_start = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(150)
        ->truncateString($test_results);
      throw new Exception(
        sprintf(
          "%s\n\n%s",
          pht('Failed to load XUnit report; Input starts with:'),
          $input_start));
    }

    $results = array();
    $testcases = $xunit_dom->getElementsByTagName('testcase');
    foreach ($testcases as $testcase) {
      $classname = $testcase->getAttribute('classname');
      $name = $testcase->getAttribute('name');
      $time = $testcase->getAttribute('time');

      $status = ArcanistUnitTestResult::RESULT_PASS;
      $user_data = '';

      // A skipped test is a test which was ignored using framework
      // mechanisms (e.g. @skip decorator)
      $skipped = $testcase->getElementsByTagName('skipped');
      if ($skipped->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_SKIP;
        $messages = array();
        for ($ii = 0; $ii < $skipped->length; $ii++) {
          $messages[] = trim($skipped->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages);
      }

      // Failure is a test which the code has explicitly failed by using
      // the mechanisms for that purpose. e.g., via an assertEquals
      $failures = $testcase->getElementsByTagName('failure');
      if ($failures->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
        $messages = array();
        for ($ii = 0; $ii < $failures->length; $ii++) {
          $messages[] = trim($failures->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      // An errored test is one that had an unanticipated problem. e.g., an
      // unchecked throwable, or a problem with an implementation of the test.
      $errors = $testcase->getElementsByTagName('error');
      if ($errors->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_BROKEN;
        $messages = array();
        for ($ii = 0; $ii < $errors->length; $ii++) {
          $messages[] = trim($errors->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      $result = new ArcanistUnitTestResult();
      $result->setName($classname.'.'.$name);
      $result->setResult($status);
      $result->setDuration($time);
      $result->setUserData($user_data);

      $results[] = $result;
    }

    return $results;
  }

}
