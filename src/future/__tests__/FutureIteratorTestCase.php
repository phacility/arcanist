<?php

final class FutureIteratorTestCase extends PhutilTestCase {

  public function testAddingFuture() {
    $bin = $this->getSupportExecutable('cat');

    $future1 = new ExecFuture('php -f %R', $bin);
    $future2 = new ExecFuture('php -f %R', $bin);

    $iterator = new FutureIterator(array($future1));
    $iterator->limit(2);

    $results = array();
    foreach ($iterator as $future) {
      if ($future === $future1) {
        $iterator->addFuture($future2);
      }
      $results[] = $future->resolve();
    }

    $this->assertEqual(2, count($results));
  }

}
