<?php

final class PhutilGitsprintfTestCase extends PhutilTestCase {

  public function testHgsprintf() {
    $selectors = array(
      'HEAD' => 'HEAD',
      'master' => 'master',
      'a..b' => 'a..b',
      'feature^' => 'feature^',
      '--flag' => false,
    );

    foreach ($selectors as $input => $expect) {
      $caught = null;

      try {
        $output = gitsprintf('%s', $input);
      } catch (Exception $ex) {
        $caught = $ex;
      } catch (Throwable $ex) {
        $caught = $ex;
      }

      if ($caught !== null) {
        $actual = false;
      } else {
        $actual = $output;
      }

      $this->assertEqual(
        $expect,
        $actual,
        pht(
          'Result for input "%s".',
          $input));
    }
  }

}
