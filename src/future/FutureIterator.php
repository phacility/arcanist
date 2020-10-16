<?php

/**
 * FutureIterator aggregates @{class:Future}s and allows you to respond to them
 * in the order they resolve. This is useful because it minimizes the amount of
 * time your program spends waiting on parallel processes.
 *
 *   $futures = array(
 *     'a.txt' => new ExecFuture('wc -c a.txt'),
 *     'b.txt' => new ExecFuture('wc -c b.txt'),
 *     'c.txt' => new ExecFuture('wc -c c.txt'),
 *   );
 *
 *   foreach (new FutureIterator($futures) as $key => $future) {
 *     // IMPORTANT: keys are preserved but the order of elements is not. This
 *     // construct iterates over the futures in the order they resolve, so the
 *     // fastest future is the one you'll get first. This allows you to start
 *     // doing followup processing as soon as possible.
 *
 *     list($err, $stdout) = $future->resolve();
 *     do_some_processing($stdout);
 *   }
 *
 * For a general overview of futures, see @{article:Using Futures}.
 *
 * @task  basics    Basics
 * @task  config    Configuring Iteration
 * @task  iterator  Iterator Interface
 * @task  internal  Internals
 */
final class FutureIterator
  extends Phobject
  implements Iterator {

  private $hold = array();
  private $wait = array();
  private $work = array();

  private $futures = array();
  private $key;

  private $limit;

  private $timeout;
  private $isTimeout = false;
  private $hasRewound = false;


/* -(  Basics  )------------------------------------------------------------- */


  /**
   * Create a new iterator over a list of futures.
   *
   * @param list  List of @{class:Future}s to resolve.
   * @task basics
   */
  public function __construct(array $futures) {
    assert_instances_of($futures, 'Future');

    foreach ($futures as $map_key => $future) {
      $future->setFutureKey($map_key);
      $this->addFuture($future);
    }
  }


  /**
   * Block until all futures resolve.
   *
   * @return void
   * @task basics
   */
  public function resolveAll() {
    // If a caller breaks out of a "foreach" and then calls "resolveAll()",
    // interpret it to mean that we should iterate over whatever futures
    // remain.

    if ($this->hasRewound) {
      while ($this->valid()) {
        $this->next();
      }
    } else {
      iterator_to_array($this);
    }
  }

  /**
   * Add another future to the set of futures. This is useful if you have a
   * set of futures to run mostly in parallel, but some futures depend on
   * others.
   *
   * @param Future  @{class:Future} to add to iterator
   * @task basics
   */
  public function addFuture(Future $future) {
    $key = $future->getFutureKey();

    if (isset($this->futures[$key])) {
      throw new Exception(
        pht(
          'This future graph already has a future with key "%s". Each '.
          'future must have a unique key.',
          $key));
    }

    $this->futures[$key] = $future;
    $this->hold[$key] = $key;

    return $this;
  }


/* -(  Configuring Iteration  )---------------------------------------------- */


  /**
   * Set a maximum amount of time you want to wait before the iterator will
   * yield a result. If no future has resolved yet, the iterator will yield
   * null for key and value. Among other potential uses, you can use this to
   * show some busy indicator:
   *
   *   $futures = id(new FutureIterator($futures))
   *     ->setUpdateInterval(1);
   *   foreach ($futures as $future) {
   *     if ($future === null) {
   *       echo "Still working...\n";
   *     } else {
   *       // ...
   *     }
   *   }
   *
   * This will echo "Still working..." once per second as long as futures are
   * resolving. By default, FutureIterator never yields null.
   *
   * @param float Maximum number of seconds to block waiting on futures before
   *              yielding null.
   * @return this
   *
   * @task config
   */
  public function setUpdateInterval($interval) {
    $this->timeout = $interval;
    return $this;
  }


  /**
   * Limit the number of simultaneously executing futures.
   *
   *  $futures = id(new FutureIterator($futures))
   *    ->limit(4);
   *  foreach ($futures as $future) {
   *    // Run no more than 4 futures simultaneously.
   *  }
   *
   * @param int Maximum number of simultaneous jobs allowed.
   * @return this
   *
   * @task config
   */
  public function limit($max) {
    $this->limit = $max;
    return $this;
  }


  public function setMaximumWorkingSetSize($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function getMaximumWorkingSetSize() {
    return $this->limit;
  }

/* -(  Iterator Interface  )------------------------------------------------- */


  /**
   * @task iterator
   */
  public function rewind() {
    if ($this->hasRewound) {
      throw new Exception(
        pht('Future graphs can not be rewound.'));
    }
    $this->hasRewound = true;

    $this->next();
  }

  /**
   * @task iterator
   */
  public function next() {
    // See T13572. If we previously resolved and returned a Future, release
    // it now. This prevents us from holding Futures indefinitely when callers
    // like FuturePool build long-lived iterators and keep adding new Futures
    // to them.
    if ($this->key !== null) {
      unset($this->futures[$this->key]);
      $this->key = null;
    }

    $this->updateWorkingSet();

    if (!$this->work) {
      return;
    }

    $start = microtime(true);
    $timeout = $this->timeout;
    $this->isTimeout = false;

    $working_set = array_select_keys($this->futures, $this->work);

    while (true) {
      // Update every future first. This is a no-op on futures which have
      // already resolved or failed, but we want to give futures an
      // opportunity to make progress even if we can resolve something.

      foreach ($working_set as $future_key => $future) {
        $future->updateFuture();
      }

      // Check if any future has resolved or failed. If we have any such
      // futures, we'll return the first one from the iterator.

      $resolve_key = null;
      foreach ($working_set as $future_key => $future) {
        if ($future->canResolve()) {
          $resolve_key = $future_key;
          break;
        }
      }

      // We've found a future to resolve, so we're done here for now.

      if ($resolve_key !== null) {
        $this->moveFutureToDone($resolve_key);
        return;
      }

      // We don't have any futures to resolve yet. Check if we're reached
      // an update interval.

      $wait_time = 1;
      if ($timeout !== null) {
        $elapsed = microtime(true) - $start;

        if ($elapsed > $timeout) {
          $this->isTimeout = true;
          return;
        }

        $wait_time = min($wait_time, $timeout - $elapsed);
      }

      // We're going to wait. If possible, we'd like to wait with sockets.
      // If we can't, we'll just sleep.

      $read_sockets = array();
      $write_sockets = array();
      foreach ($working_set as $future_key => $future) {
        $sockets = $future->getReadSockets();
        foreach ($sockets as $socket) {
          $read_sockets[] = $socket;
        }

        $sockets = $future->getWriteSockets();
        foreach ($sockets as $socket) {
          $write_sockets[] = $socket;
        }
      }

      $use_sockets = ($read_sockets || $write_sockets);
      if ($use_sockets) {
        foreach ($working_set as $future) {
          $wait_time = min($wait_time, $future->getDefaultWait());
        }
        $this->waitForSockets($read_sockets, $write_sockets, $wait_time);
      } else {
        usleep(1000);
      }
    }
  }

  /**
   * @task iterator
   */
  public function current() {
    if ($this->isTimeout) {
      return null;
    }
    return $this->futures[$this->key];
  }

  /**
   * @task iterator
   */
  public function key() {
    if ($this->isTimeout) {
      return null;
    }
    return $this->key;
  }

  /**
   * @task iterator
   */
  public function valid() {
    if ($this->isTimeout) {
      return true;
    }
    return ($this->key !== null);
  }


/* -(  Internals  )---------------------------------------------------------- */

  /**
   * @task internal
   */
  protected function updateWorkingSet() {
    $limit = $this->getMaximumWorkingSetSize();
    $work_count = count($this->work);

    // If we're already working on the maximum number of futures, we just have
    // to wait for something to resolve. There's no benefit to updating the
    // queue since we can never make any meaningful progress.

    if ($limit) {
      if ($work_count >= $limit) {
        return;
      }
    }

    // If any futures that are currently held are no longer blocked by
    // dependencies, move them from "hold" to "wait".

    foreach ($this->hold as $future_key) {
      if (!$this->canMoveFutureToWait($future_key)) {
        continue;
      }

      $this->moveFutureToWait($future_key);
    }

    $wait_count = count($this->wait);
    $hold_count = count($this->hold);

    if (!$work_count && !$wait_count && $hold_count) {
      throw new Exception(
        pht(
          'Future graph is stalled: some futures are held, but no futures '.
          'are waiting or working. The graph can never resolve.'));
    }

    // Figure out how many futures we can start. If we don't have a limit,
    // we can start every waiting future. If we do have a limit, we can only
    // start as many futures as we have slots for.

    if ($limit) {
      $work_limit = min($limit, $wait_count);
    } else {
      $work_limit = $wait_count;
    }

    // If we're ready to start futures, start them now.

    if ($work_limit) {
      foreach ($this->wait as $future_key) {
        $this->moveFutureToWork($future_key);

        $work_limit--;
        if (!$work_limit) {
          return;
        }
      }
    }

  }

  private function canMoveFutureToWait($future_key) {
    return true;
  }

  private function moveFutureToWait($future_key) {
    unset($this->hold[$future_key]);
    $this->wait[$future_key] = $future_key;
  }

  private function moveFutureToWork($future_key) {
    unset($this->wait[$future_key]);
    $this->work[$future_key] = $future_key;

    $future = $this->futures[$future_key];

    if (!$future->getHasFutureStarted()) {
      $future
        ->setRaiseExceptionOnStart(false)
        ->start();
    }
  }

  private function moveFutureToDone($future_key) {
    $this->key = $future_key;
    unset($this->work[$future_key]);

    // Before we return, do another working set update so we start any
    // futures that are ready to go as soon as we can.

    $this->updateWorkingSet();
  }

  /**
   * Wait for activity on one of several sockets.
   *
   * @param  list  List of sockets expected to become readable.
   * @param  list  List of sockets expected to become writable.
   * @param  float Timeout, in seconds.
   * @return void
   */
  private function waitForSockets(
    array $read_list,
    array $write_list,
    $timeout = 1.0) {

    static $handler_installed = false;

    if (!$handler_installed) {
      // If we're spawning child processes, we need to install a signal handler
      // here to catch cases like execing '(sleep 60 &) &' where the child
      // exits but a socket is kept open. But we don't actually need to do
      // anything because the SIGCHLD will interrupt the stream_select(), as
      // long as we have a handler registered.
      if (function_exists('pcntl_signal')) {
        if (!pcntl_signal(SIGCHLD, array(__CLASS__, 'handleSIGCHLD'))) {
          throw new Exception(pht('Failed to install signal handler!'));
        }
      }
      $handler_installed = true;
    }

    $timeout_sec = (int)$timeout;
    $timeout_usec = (int)(1000000 * ($timeout - $timeout_sec));

    $exceptfds = array();
    $ok = @stream_select(
      $read_list,
      $write_list,
      $exceptfds,
      $timeout_sec,
      $timeout_usec);

    if ($ok === false) {
      // Hopefully, means we received a SIGCHLD. In the worst case, we degrade
      // to a busy wait.
    }
  }

  public static function handleSIGCHLD($signo) {
    // This function is a dummy, we just need to have some handler registered
    // so that PHP will get interrupted during "stream_select()". If we don't
    // register a handler, "stream_select()" won't fail.
  }


}
