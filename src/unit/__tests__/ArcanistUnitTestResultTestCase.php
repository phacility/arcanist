<?php

final class ArcanistUnitTestResultTestCase extends PhutilTestCase {

  public function testCoverageMerges() {
    $cases = array(
      array(
        'coverage' => array(),
        'expect' => null,
      ),
      array(
        'coverage' => array(
          'UUUNCNC',
        ),
        'expect' => 'UUUNCNC',
      ),
      array(
        'coverage' => array(
          'UUCUUU',
          'UUUUCU',
        ),
        'expect' => 'UUCUCU',
      ),
      array(
        'coverage' => array(
          'UUCCCU',
          'UUUCCCNNNC',
        ),
        'expect' => 'UUCCCCNNNC',
      ),
    );

    foreach ($cases as $case) {
      $input = $case['coverage'];
      $expect = $case['expect'];

      $actual = ArcanistUnitTestResult::mergeCoverage($input);

      $this->assertEqual($expect, $actual);
    }
  }

}
