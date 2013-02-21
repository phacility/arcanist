<?php

final class ArcanistBritishTestCase extends ArcanistTestCase {

  public function testCompletion() {
    $this->assertCompletion(
      array('land', 'amend'),
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
