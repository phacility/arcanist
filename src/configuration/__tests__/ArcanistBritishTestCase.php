<?php

final class ArcanistBritishTestCase extends ArcanistTestCase {

  public function testCompletion() {
    $this->assertCompletion(
      array('land'),
      'alnd',
      array('land', 'amend'));

    $this->assertCompletion(
      array('branch'),
      'brnach',
      array('branch', 'browse'));

    $this->assertCompletion(
      array(),
      'test',
      array('list', 'unit'));

    $this->assertCompletion(
      array('list'),
      'lists',
      array('list'));

    $this->assertCompletion(
      array('diff'),
      'dfif',
      array('diff'));

    $this->assertCompletion(
      array('unit'),
      'uint',
      array('unit', 'lint', 'list'));

    $this->assertCompletion(
      array('list', 'lint'),
      'nilt',
      array('unit', 'lint', 'list'));
  }

  private function assertCompletion($expect, $input, $commands) {
    $result = ArcanistConfiguration::correctCommandSpelling(
      $input,
      $commands,
      2);

    sort($result);
    sort($expect);

    $commands = implode(', ', $commands);

    $this->assertEqual(
      $expect,
      $result,
      "Correction of {$input} against: {$commands}");
  }


}
