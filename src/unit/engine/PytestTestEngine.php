<?php

/**
 * Very basic 'py.test' unit test engine wrapper.
 *
 * @group unitrun
 */
final class PytestTestEngine extends ArcanistBaseUnitTestEngine {

  public function run() {
    $junit_tmp = new TempFile();

    $cmd_line = csprintf('py.test --junitxml=%s',
                         $junit_tmp);

    $future = new ExecFuture('%C', $cmd_line);
    list($stdout, $stderr) = $future->resolvex();

    return $this->parseTestResults($junit_tmp);
  }

  public function parseTestResults($junit_tmp) {
    // xunit xsd: https://gist.github.com/959290
    $xunit_dom = new DOMDocument();
    $xunit_dom->loadXML(Filesystem::readFile($junit_tmp));

    $results = array();
    $testcases = $xunit_dom->getElementsByTagName("testcase");
    foreach ($testcases as $testcase) {
      $classname = $testcase->getAttribute("classname");
      $name = $testcase->getAttribute("name");
      $time = $testcase->getAttribute("time");

      $status = ArcanistUnitTestResult::RESULT_PASS;
      $user_data = "";

      // A skipped test is a test which was ignored using framework
      // mechanizms (e.g. @skip decorator)
      $skipped = $testcase->getElementsByTagName("skipped");
      if ($skipped->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_SKIP;
        $messages = array();
        for ($ii = 0; $ii < $skipped->length; $ii++) {
          $messages[] = trim($skipped->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages);
      }

      // Failure is a test which the code has explicitly failed by using
      // the mechanizms for that purpose. e.g., via an assertEquals
      $failures = $testcase->getElementsByTagName("failure");
      if ($failures->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_FAIL;
        $messages = array();
        for ($ii = 0; $ii < $failures->length; $ii++) {
          $messages[] = trim($failures->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      // An errored test is one that had an unanticipated problem. e.g., an
      // unchecked throwable, or a problem with an implementation of the
      // test.
      $errors = $testcase->getElementsByTagName("error");
      if ($errors->length > 0) {
        $status = ArcanistUnitTestResult::RESULT_BROKEN;
        $messages = array();
        for ($ii = 0; $ii < $errors->length; $ii++) {
          $messages[] = trim($errors->item($ii)->nodeValue, " \n");
        }

        $user_data .= implode("\n", $messages)."\n";
      }

      $result = new ArcanistUnitTestResult();
      $result->setName($classname.".".$name);
      $result->setResult($status);
      $result->setDuration($time);
      $result->setUserData($user_data);

      $results[] = $result;
    }

    return $results;
  }

}
