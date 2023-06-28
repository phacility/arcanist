<?php

final class ExecFutureTestCase extends PhutilTestCase {

  public function testEmptyWrite() {
    // NOTE: This is mostly testing that we don't hang while doing an empty
    // write.

    list($stdout) = $this->newCat()
      ->write('')
      ->resolvex();

    $this->assertEqual('', $stdout);
  }

  private function newCat() {
    $bin = $this->getSupportExecutable('cat');
    return new ExecFuture('php -f %R', $bin);
  }

  private function newSleep($duration) {
    $bin = $this->getSupportExecutable('sleep');
    return new ExecFuture('php -f %R -- %s', $bin, $duration);
  }

  public function testKeepPipe() {
    // NOTE: This is mostly testing the semantics of $keep_pipe in write().

    list($stdout) = $this->newCat()
      ->write('', true)
      ->start()
      ->write('x', true)
      ->write('y', true)
      ->write('z', false)
      ->resolvex();

    $this->assertEqual('xyz', $stdout);
  }

  public function testLargeBuffer() {
    // NOTE: This is mostly a coverage test to hit branches where we're still
    // flushing a buffer.

    $data = str_repeat('x', 1024 * 1024 * 4);
    list($stdout) = $this->newCat()->write($data)->resolvex();

    $this->assertEqual($data, $stdout);
  }

  public function testBufferLimit() {
    $data = str_repeat('x', 1024 * 1024);
    list($stdout) = $this->newCat()
      ->setStdoutSizeLimit(1024)
      ->write($data)
      ->resolvex();

    $this->assertEqual(substr($data, 0, 1024), $stdout);
  }

  public function testResolveTimeoutTestShouldRunLessThan1Sec() {
    // NOTE: This tests interactions between the resolve() timeout and the
    // resolution timeout, which are somewhat similar but not identical.

    $future = $this->newSleep(32000);
    $future->setTimeout(32000);

    // We expect this to return in 0.01s.
    $iterator = (new FutureIterator(array($future)))
      ->setUpdateInterval(0.01);
    foreach ($iterator as $resolved_result) {
      $result = $resolved_result;
      break;
    }
    $this->assertEqual($result, null);

    // We expect this to now force the time out / kill immediately. If we don't
    // do this, we'll hang when exiting until our subprocess exits (32000
    // seconds!)
    $future->setTimeout(0.01);
    $iterator->resolveAll();
  }

  public function testTerminateWithoutStart() {
    // We never start this future, but it should be fine to kill a future from
    // any state.
    $future = $this->newSleep(1);
    $future->resolveKill();

    $this->assertTrue(true);
  }

  public function testTimeoutTestShouldRunLessThan1Sec() {
    // NOTE: This is partly testing that we choose appropriate select wait
    // times; this test should run for significantly less than 1 second.

    $future = $this->newSleep(32000);
    list($err) = $future->setTimeout(0.01)->resolve();

    $this->assertTrue($err > 0);
    $this->assertTrue($future->getWasKilledByTimeout());
  }

  public function testMultipleTimeoutsTestShouldRunLessThan1Sec() {
    $futures = array();
    for ($ii = 0; $ii < 4; $ii++) {
      $futures[] = $this->newSleep(32000)->setTimeout(0.01);
    }

    foreach (new FutureIterator($futures) as $future) {
      list($err) = $future->resolve();

      $this->assertTrue($err > 0);
      $this->assertTrue($future->getWasKilledByTimeout());
    }
  }

  public function testMultipleResolves() {
    // It should be safe to call resolve(), resolvex(), resolveKill(), etc.,
    // as many times as you want on the same process.
    $bin = $this->getSupportExecutable('echo');

    $future = new ExecFuture('php -f %R -- quack', $bin);
    $future->resolve();
    $future->resolvex();
    list($err) = $future->resolveKill();

    $this->assertEqual(0, $err);
  }

  public function testEscaping() {
    $inputs = array(
      'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
      '!@#$%^&*()-=_+`~,.<>/?[]{};\':"|',
      '',
      ' ',
      'x y',
      '%PATH%',
      '"',
      '""',
      'a "b" c',
      '\\',
      '\\a',
      'a\\',
      '\\a\\',
      'a\\a',
      '\\\\',
      '\\"',
    );

    $bin = $this->getSupportExecutable('echo');

    foreach ($inputs as $input) {
      if (!is_array($input)) {
        $input = array($input);
      }

      list($stdout) = execx(
        'php -f %R -- %Ls',
        $bin,
        $input);

      $stdout = explode("\n", $stdout);
      $output = array();
      foreach ($stdout as $line) {
        $output[] = stripcslashes($line);
      }

      $this->assertEqual(
        $input,
        $output,
        pht(
          'Arguments are preserved for input: %s',
          implode(' ', $input)));
    }
  }

  public function testReadBuffering() {
    $str_len_8 = 'abcdefgh';
    $str_len_4 = 'abcd';

    // This is a write/read with no read buffer.
    $future = $this->newCat();
    $future->write($str_len_8);

    do {
      $future->isReady();
      list($read) = $future->read();
      if (strlen($read)) {
        break;
      }
    } while (true);

    // We expect to get the entire string back in the read.
    $this->assertEqual($str_len_8, $read);
    $future->resolve();


    // This is a write/read with a read buffer.
    $future = $this->newCat();
    $future->write($str_len_8);

    // Set the read buffer size.
    $future->setReadBufferSize(4);
    do {
      $future->isReady();
      list($read) = $future->read();
      if (strlen($read)) {
        break;
      }
    } while (true);

    // We expect to get the entire string back in the read.
    $this->assertEqual($str_len_4, $read);

    // Remove the limit so we can resolve the future.
    $future->setReadBufferSize(null);
    $future->resolve();
  }

}
