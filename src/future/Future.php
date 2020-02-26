<?php

/**
 * A 'future' or 'promise' is an object which represents the result of some
 * pending computation. For a more complete overview of futures, see
 * @{article:Using Futures}.
 */
abstract class Future extends Phobject {

  private $hasResult = false;
  private $result;

  protected $exception;

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

    $graph = new FutureIterator(array($this));
    $graph->resolveAll();

    if ($this->exception) {
      throw $this->exception;
    }

    return $this->getResult();
  }

  public function setException(Exception $ex) {
    $this->exception = $ex;
    return $this;
  }

  public function getException() {
    return $this->exception;
  }

  /**
   * Retrieve a list of sockets which we can wait to become readable while
   * a future is resolving. If your future has sockets which can be
   * `select()`ed, return them here (or in @{method:getWriteSockets}) to make
   * the resolve loop  do a `select()`. If you do not return sockets in either
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
    $this->isReady();
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

    return $this;
  }

  final protected function hasResult() {
    return $this->hasResult;
  }

}
