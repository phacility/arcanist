<?php

final class MethodCallFutureTestCase extends PhutilTestCase {

  public function testMethodCallFutureSums() {
    $future = new MethodCallFuture($this, 'getSum', 1, 2, 3);
    $result = $future->resolve();

    $this->assertEqual(6, $result, pht('MethodCallFuture: getSum(1, 2, 3)'));

    $future = new MethodCallFuture($this, 'getSum');
    $result = $future->resolve();

    $this->assertEqual(0, $result, pht('MethodCallFuture: getSum()'));
  }

  public function testMethodCallFutureExceptions() {
    $future = new MethodCallFuture($this, 'raiseException');

    // See T13666. Using "FutureIterator" to advance the future until it is
    // ready to resolve should NOT throw an exception.

    foreach (new FutureIterator(array($future)) as $resolvable) {
      // Continue below...
    }

    $caught = null;
    try {
      $future->resolve();
    } catch (PhutilMethodNotImplementedException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof PhutilMethodNotImplementedException),
      pht('MethodCallFuture: exceptions raise at resolution.'));
  }

  public function getSum(/* ... */) {
    $args = func_get_args();

    $sum = 0;
    foreach ($args as $arg) {
      $sum += $arg;
    }

    return $sum;
  }

  public function raiseException() {
    // We just want to throw any narrow exception so the test isn't catching
    // too broad an exception type. This is simulating some exception during
    // future resolution.
    throw new PhutilMethodNotImplementedException();
  }

}
