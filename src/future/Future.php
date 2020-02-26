<?php

/**
 * A 'future' or 'promise' is an object which represents the result of some
 * pending computation. For a more complete overview of futures, see
 * @{article:Using Futures}.
 */
abstract class Future extends Phobject {

  protected $result;
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
   * Retrieve the final result of the future. This method will be called after
   * the future is ready (as per @{method:isReady}) but before results are
   * passed back to the caller. The major use of this function is that you can
   * override it in subclasses to do postprocessing or error checking, which is
   * particularly useful if building application-specific futures on top of
   * primitive transport futures (like @{class:CurlFuture} and
   * @{class:ExecFuture}) which can make it tricky to hook this logic into the
   * main pipeline.
   *
   * @return mixed   Final resolution of this future.
   */
  protected function getResult() {
    return $this->result;
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

}
