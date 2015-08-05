<?php

final class ArcanistBritishTestCase extends PhutilTestCase {

  public function testCommandCompletion() {
    $this->assertCommandCompletion(
      array('land'),
      'alnd',
      array('land', 'amend'));

    $this->assertCommandCompletion(
      array('branch'),
      'brnach',
      array('branch', 'browse'));

    $this->assertCommandCompletion(
      array(),
      'test',
      array('list', 'unit'));

    $this->assertCommandCompletion(
      array('list'),
      'lists',
      array('list'));

    $this->assertCommandCompletion(
      array('diff'),
      'dfif',
      array('diff'));

    $this->assertCommandCompletion(
      array('unit'),
      'uint',
      array('unit', 'lint', 'list'));

    $this->assertCommandCompletion(
      array('list', 'lint'),
      'nilt',
      array('unit', 'lint', 'list'));
  }

  private function assertCommandCompletion($expect, $input, $commands) {
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
      pht('Correction of %s against: %s', $input, $commands));
  }

  public function testArgumentCompletion() {
    $this->assertArgumentCompletion(
      array('nolint'),
      'no-lint',
      array('nolint', 'nounit'));

    $this->assertArgumentCompletion(
      array('reviewers'),
      'reviewer',
      array('reviewers', 'cc'));

    $this->assertArgumentCompletion(
      array(),
      'onlint',
      array('nolint'));

    $this->assertArgumentCompletion(
      array(),
      'nolind',
      array('nolint'));
  }

  private function assertArgumentCompletion($expect, $input, $arguments) {
    $result = ArcanistConfiguration::correctArgumentSpelling(
      $input,
      $arguments);

    sort($result);
    sort($expect);

    $arguments = implode(', ', $arguments);

    $this->assertEqual(
      $expect,
      $result,
      pht('Correction of %s against: %s', $input, $arguments));
  }

}
