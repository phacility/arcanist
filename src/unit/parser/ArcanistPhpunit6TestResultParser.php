<?php

/**
 * PHPUnit 6 Result Parsing utility
 *
 * For an example on how to integrate with your test engine, see
 * @{class:PhpunitTestEngine}.

 * This wrapper was created because original PhpunitTestEngine does not work
 * with PHPUnit 6 version. Wrapper is not officially supported by Phacility.
 */
final class ArcanistPhpunit6TestResultParser extends ArcanistTestResultParser {

  /**
   * Parse test results from phpunit XML report
   *
   * @param string $path Path to test
   * @param string $test_results String containing phpunit XML report
   *
   * @return array
   */
  public function parseTestResults($path, $test_results) {
    if (!$test_results) {
      $result = id(new ArcanistUnitTestResult())
        ->setName($path)
        ->setUserData($this->stderr)
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
      return array($result);
    }

    $report = simplexml_load_string($test_results);

    // coverage is for all testcases in the executed $path
    $coverage = array();
    if ($this->enableCoverage !== false) {
      $coverage = $this->readCoverage();
    }

    $last_test_finished = true;

    $results = array();
    foreach ($report->testsuite as $test_suite) {
      $status = ArcanistUnitTestResult::RESULT_PASS;
      $user_data = '';

      if ((int)$test_suite['failures'] > 0) {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
        foreach ($test_suite->testcase as $test_case) {
          foreach ($test_case->failure as $failure) {
              $user_data .= sprintf(
                "\n%s",
                (string)$failure);
          }
        }
      } else if ($test_suite['errors'] > 0) {
        $status = ArcanistUnitTestResult::RESULT_BROKEN;
        foreach ($test_suite->testcase as $test_case) {
          foreach ($test_case->error as $error) {
              $user_data .= sprintf(
                "\n%s",
                (string)$error);
          }
        }
      }

      $name = preg_replace('/ \(.*\)/s', '', $test_suite['name']);

      $result = new ArcanistUnitTestResult();
      $result->setName($name);
      $result->setResult($status);
      $result->setDuration((float)$test_suite['time']);
      $result->setCoverage($coverage);
      $result->setUserData($user_data);

      $results[] = $result;
      $last_test_finished = true;
    }

    if (!$last_test_finished) {
      $results[] = id(new ArcanistUnitTestResult())
        ->setName($test_suite['name']) // use last event
        ->setUserData($this->stderr)
        ->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
    }
    return $results;
  }

  /**
   * Read the coverage from phpunit generated clover report
   *
   * @return array
   */
  private function readCoverage() {
    $test_results = Filesystem::readFile($this->coverageFile);
    if (empty($test_results)) {
      return array();
    }

    $coverage_dom = new DOMDocument();
    $coverage_dom->loadXML($test_results);

    $reports = array();
    $files = $coverage_dom->getElementsByTagName('file');

    foreach ($files as $file) {
      $class_path = $file->getAttribute('name');
      if (empty($this->affectedTests[$class_path])) {
        continue;
      }
      $test_path = $this->affectedTests[$file->getAttribute('name')];
      // get total line count in file
      $line_count = count(file($class_path));

      $coverage = '';
      $any_line_covered = false;
      $start_line = 1;
      $lines = $file->getElementsByTagName('line');

      $coverage = str_repeat('N', $line_count);
      foreach ($lines as $line) {
        if ($line->getAttribute('type') != 'stmt') {
          continue;
        }
        if ((int)$line->getAttribute('count') > 0) {
          $is_covered = 'C';
          $any_line_covered = true;
        } else {
          $is_covered = 'U';
        }
        $line_no = (int)$line->getAttribute('num');
        $coverage[$line_no - 1] = $is_covered;
      }

      // Sometimes the Clover coverage gives false positives on uncovered lines
      // when the file wasn't actually part of the test. This filters out files
      // with no coverage which helps give more accurate overall results.
      if ($any_line_covered) {
        $len = strlen($this->projectRoot.DIRECTORY_SEPARATOR);
        $class_path = substr($class_path, $len);
        $reports[$class_path] = $coverage;
      }
    }

    return $reports;
  }

}
