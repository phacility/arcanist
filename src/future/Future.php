<?php

/**
 * A 'future' or 'promise' is an object which represents the result of some
 * pending computation. For a more complete overview of futures, see
 * @{article:Using Futures}.
 */
abstract class Future extends Phobject {

  private $hasResult = false;
  private $hasStarted = false;
  private $hasEnded = false;
  private $result;
  private $exception;
  private $futureKey;
  private $serviceProfilerCallID;
  private $raiseExceptionOnStart = true;
  private static $nextKey = 1;

  /**
   * Is this future's process complete? Specifically, can this future be
   * resolved without blocking?
   *
   * @return bool  If true, the external process is complete and resolving this
   *               future will not block.
   */
  abstract public function isReady();

  /**
   * Resolve a future and return its result, blocking until the result is ready
   * if necessary.
   *
   * @return wild Future result.
   */
  public function resolve() {
    $args = func_get_args();
    if (count($args)) {
      throw new Exception(
        pht(
          'Parameter "timeout" to "Future->resolve()" is no longer '.
          'supported. Update the caller so it no longer passes a '.
          'timeout.'));
    }

    if (!$this->canResolve()) {
      $graph = new FutureIterator(array($this));
      $graph->resolveAll();
    }

    if ($this->hasException()) {
      throw $this->getException();
    }

    return $this->getResult();
  }

  final public function updateFuture() {
    if ($this->canResolve()) {
      return;
    }

    try {
      $this->isReady();
    } catch (Exception $ex) {
      $this->setException($ex);
    } catch (Throwable $ex) {
      $this->setException($ex);
    }
  }

  private function startServiceProfiler() {

    // NOTE: This is a soft dependency so that we don't need to build the
    // ServiceProfiler into the Phage agent. Normally, this class is always
    // available.

    if (!class_exists('PhutilServiceProfiler')) {
      return;
    }

    $params = $this->getServiceProfilerStartParameters();

    if ($params === null) {
      return;
    }

    $profiler = PhutilServiceProfiler::getInstance();
    $call_id = $profiler->beginServiceCall($params);

    $this->serviceProfilerCallID = $call_id;
  }

  private function endServiceProfiler() {
    $call_id = $this->serviceProfilerCallID;
    if ($call_id === null) {
      return;
    }

    $params = $this->getServiceProfilerResultParameters();

    $profiler = PhutilServiceProfiler::getInstance();
    $profiler->endServiceCall($call_id, $params);
  }

  protected function getServiceProfilerStartParameters() {
    return array();
  }

  protected function getServiceProfilerResultParameters() {
    return array();
  }


  /**
   * Retrieve a list of sockets which we can wait to become readable while
   * a future is resolving. If your future has sockets which can be
   * `select()`ed, return them here (or in @{method:getWriteSockets}) to make
   * the resolve loop do a `select()`. If you do not return sockets in either
   * case, you'll get a busy wait.
   *
   * @return list  A list of sockets which we expect to become readable.
   */
  public function getReadSockets() {
    return array();
  }


  /**
   * Retrieve a list of sockets which we can wait to become writable while a
   * future is resolving. See @{method:getReadSockets}.
   *
   * @return list  A list of sockets which we expect to become writable.
   */
  public function getWriteSockets() {
    return array();
  }


  /**
   * Default amount of time to wait on stream select for this future. Normally
   * 1 second is fine, but if the future has a timeout sooner than that it
   * should return the amount of time left before the timeout.
   */
  public function getDefaultWait() {
    return 1;
  }

  public function start() {
    if ($this->hasStarted) {
      throw new Exception(
        pht(
          'Future has already started; futures can not start more '.
          'than once.'));
    }
    $this->hasStarted = true;

    $this->startServiceProfiler();

    $this->updateFuture();

    if ($this->raiseExceptionOnStart) {
      if ($this->hasException()) {
        throw $this->getException();
      }
    }

    return $this;
  }

  /**
   * Retrieve the final result of the future.
   *
   * @return wild Final resolution of this future.
   */
  final protected function getResult() {
    if (!$this->hasResult()) {
      throw new Exception(
        pht(
          'Future has not yet resolved. Resolve futures before retrieving '.
          'results.'));
    }

    return $this->result;
  }

  final protected function setResult($result) {
    if ($this->hasResult()) {
      throw new Exception(
        pht(
          'Future has already resolved. Futures may not resolve more than '.
          'once.'));
    }

    $this->hasResult = true;
    $this->result = $result;

    $this->endFuture();

    return $this;
  }

  final public function hasResult() {
    return $this->hasResult;
  }

  private function setException($exception) {
    // NOTE: The parameter may be an Exception or a Throwable.
    $this->exception = $exception;

    $this->endFuture();

    return $this;
  }

  private function getException() {
    return $this->exception;
  }

  final public function hasException() {
    return ($this->exception !== null);
  }

  final public function setFutureKey($key) {
    if ($this->futureKey !== null) {
      throw new Exception(
        pht(
          'Future already has a key ("%s") assigned.',
          $key));
    }

    $this->futureKey = $key;

    return $this;
  }

  final public function getFutureKey() {
    if ($this->futureKey === null) {
      $this->futureKey = sprintf('Future/%d', self::$nextKey++);
    }

    return $this->futureKey;
  }

  final public function setRaiseExceptionOnStart($raise) {
    $this->raiseExceptionOnStart = $raise;
    return $this;
  }

  final public function getHasFutureStarted() {
    return $this->hasStarted;
  }

  final public function canResolve() {
    if ($this->hasResult()) {
      return true;
    }

    if ($this->hasException()) {
      return true;
    }

    return false;
  }

  private function endFuture() {
    if ($this->hasEnded) {
      throw new Exception(
        pht(
          'Future has already ended; futures can not end more '.
          'than once.'));
    }
    $this->hasEnded = true;

    $this->endServiceProfiler();
  }

}
