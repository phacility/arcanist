<?php

/**
 * Test cases for @{class:ArcanistApacheLicenseLinter}.
 *
 * @group testcase
 */
final class ArcanistApacheLicenseLinterTestCase extends ArcanistLinterTestCase {

  public function testApacheLicenseLint() {
    $linter = new ArcanistApacheLicenseLinter();
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/apachelicense/',
      $linter,
      $working_copy);
  }

  protected function compareTransform($expected, $actual) {
    $expected = str_replace('YYYY', date('Y'), $expected);
    return parent::compareTransform($expected, $actual);
  }

}
