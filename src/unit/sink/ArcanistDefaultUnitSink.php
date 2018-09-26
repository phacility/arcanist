<?php

final class ArcanistDefaultUnitSink
  extends ArcanistUnitSink {

  const SINKKEY = 'default';

  private $lastUpdateTime;
  private $buffer = array();

  public function sinkPartialResults(array $results) {

    // We want to show the user both regular progress reports and make sure
    // that important results aren't scrolled off screen. We'll print a summary
    // at the end so it's not critical that users can never miss important
    // results, but they may (for example) want to ^C early if tests fail and
    // their fate is sealed.


    $failed = array();
    foreach ($results as $result) {
      $result_code = $result->getResult();
      switch ($result_code) {
        case ArcanistUnitTestResult::RESULT_PASS:
        case ArcanistUnitTestResult::RESULT_SKIP:
          break;
        default:
          $failed[] = $result;
          break;
      }
    }

    $now = microtime(true);

    if (!$failed) {
      if ($this->lastUpdateTime) {
        $delay = 1;
        if (($now - $this->lastUpdateTime) < $delay) {
          $this->buffer[] = $results;
          return;
        }
      }
    }

    $this->buffer[] = $results;
    $results = array_mergev($this->buffer);
    $this->buffer = array();

    $pass_count = 0;
    $skip_count = 0;
    $failed = array();
    foreach ($results as $result) {
      $result_code = $result->getResult();
      switch ($result_code) {
        case ArcanistUnitTestResult::RESULT_PASS:
          $pass_count++;
          break;
        case ArcanistUnitTestResult::RESULT_SKIP:
          $skip_count++;
          break;
        default:
          $failed[] = $result;
          break;
      }
    }

    if ($pass_count) {
      echo tsprintf(
        "%s\n",
        pht('%s tests passed.', $pass_count));
    }

    if ($skip_count) {
      echo tsprintf(
        "%s\n",
        pht('%s tests skipped.', $skip_count));
    }

    foreach ($failed as $result) {
      echo $this->getDisplayForTest($result, false);
    }

    $this->lastUpdateTime = $now;

    return $this;
  }

  public function sinkFinalResults(array $results) {

    $passed = array();
    $skipped = array();
    $failed = array();
    foreach ($results as $result) {
      $result_code = $result->getResult();
      switch ($result_code) {
        case ArcanistUnitTestResult::RESULT_PASS:
          $passed[] = $result;
          break;
        case ArcanistUnitTestResult::RESULT_SKIP:
          $skipped[] = $result;
          break;
        default:
          $failed[] = $result;
          break;
      }
    }

    echo tsprintf(
      "%s\n",
      pht('RESULT SUMMARY'));


    if ($skipped) {
      echo tsprintf(
        "%s\n",
        pht('SKIPPED TESTS'));

      foreach ($skipped as $result) {
        echo $this->getDisplayForTest($result);
      }
    }

    if ($failed) {
      echo tsprintf(
        "%s\n",
        pht('FAILED TESTS'));

      foreach ($failed as $result) {
        echo $this->getDisplayForTest($result);
      }
    }


    echo tsprintf(
      "**<bg:red> ~~~ %s </bg>**\n",
      pht(
        "%s PASSED * %s SKIPPED * %s FAILED/BROKEN/UNSTABLE",
        phutil_count($passed),
        phutil_count($skipped),
        phutil_count($failed)));
  }

  private function getDisplayForTest(ArcanistUnitTestResult $result) {

    $color = $result->getANSIColor();
    $status = $result->getResultLabel();
    $name = $result->getDisplayName();

    // TOOLSETS: Restore timing information.
    $timing = '    ';

    $output = tsprintf(
      "**<bg:".$color."> %s </bg>** %s %s\n",
      $status,
      $timing,
      $name);

    $user_data = $result->getUserData();
    if (strlen($user_data)) {
      $output = tsprintf(
        "%s%B\n",
        $output,
        $user_data);
    }

    return $output;
  }

}
