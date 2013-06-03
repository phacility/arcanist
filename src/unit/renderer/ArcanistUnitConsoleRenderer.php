<?php

/**
 * @group unit
 */
final class ArcanistUnitConsoleRenderer extends ArcanistUnitRenderer {

  public function renderUnitResult(ArcanistUnitTestResult $result) {
    $result_code = $result->getResult();

    $duration = '';
    if ($result_code == ArcanistUnitTestResult::RESULT_PASS) {
      $duration = ' '.$this->formatTestDuration($result->getDuration());
    }
    $return = sprintf(
      "  %s %s\n",
      $this->getFormattedResult($result->getResult()).$duration,
      $result->getName());

    if ($result_code != ArcanistUnitTestResult::RESULT_PASS) {
      $return .= $result->getUserData()."\n";
    }

    return $return;
  }

  public function renderPostponedResult($count) {
    return sprintf(
      "%s %s\n",
      $this->getFormattedResult(ArcanistUnitTestResult::RESULT_POSTPONED),
      pht('%d test(s)', $count));
  }

  private function getFormattedResult($result) {
    static $status_codes = array(
      ArcanistUnitTestResult::RESULT_PASS => '<bg:green>** PASS **</bg>',
      ArcanistUnitTestResult::RESULT_FAIL => '<bg:red>** FAIL **</bg>',
      ArcanistUnitTestResult::RESULT_SKIP => '<bg:yellow>** SKIP **</bg>',
      ArcanistUnitTestResult::RESULT_BROKEN => '<bg:red>** BROKEN **</bg>',
      ArcanistUnitTestResult::RESULT_UNSOUND => '<bg:yellow>** UNSOUND **</bg>',
      ArcanistUnitTestResult::RESULT_POSTPONED =>
        '<bg:yellow>** POSTPONED **</bg>',
    );
    return phutil_console_format($status_codes[$result]);
  }

  private function formatTestDuration($seconds) {
    // Very carefully define inclusive upper bounds on acceptable unit test
    // durations. Times are in milliseconds and are in increasing order.
    $star = "\xE2\x98\x85";
    if (phutil_is_windows()) {
      // Fall-back to normal asterisk for Windows consoles.
      $star = "*";
    }
    $acceptableness = array(
      50   => "<fg:green>%s</fg><fg:yellow>{$star}</fg> ",
      200  => '<fg:green>%s</fg>  ',
      500  => '<fg:yellow>%s</fg>  ',
      INF  => '<fg:red>%s</fg>  ',
    );

    $milliseconds = $seconds * 1000;
    $duration = $this->formatTime($seconds);
    foreach ($acceptableness as $upper_bound => $formatting) {
      if ($milliseconds <= $upper_bound) {
        return phutil_console_format($formatting, $duration);
      }
    }
    return phutil_console_format(end($acceptableness), $duration);
  }

  private function formatTime($seconds) {
    if ($seconds >= 60) {
      $minutes = floor($seconds / 60);
      return sprintf('%dm%02ds', $minutes, round($seconds % 60));
    }

    if ($seconds >= 1) {
      return sprintf('%4.1fs', $seconds);
    }

    $milliseconds = $seconds * 1000;
    if ($milliseconds >= 1) {
      return sprintf('%3dms', round($milliseconds));
    }

    return ' <1ms';
  }

}
