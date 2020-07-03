<?php

/**
 * Wraps another @{class:Future} and allows you to post-process its result once
 * it resolves.
 */
abstract class FutureProxy extends Future {

  private $proxied;

  public function __construct(Future $proxied = null) {
    if ($proxied) {
      $this->setProxiedFuture($proxied);
    }
  }

  public function setProxiedFuture(Future $proxied) {
    $this->proxied = $proxied;
    return $this;
  }

  protected function getProxiedFuture() {
    if (!$this->proxied) {
      throw new Exception(pht('The proxied future has not been provided yet.'));
    }
    return $this->proxied;
  }

  public function isReady() {
    if ($this->hasResult() || $this->hasException()) {
      return true;
    }

    $proxied = $this->getProxiedFuture();
    $proxied->updateFuture();

    if ($proxied->hasResult() || $proxied->hasException()) {
      try {
        $result = $proxied->resolve();
        $result = $this->didReceiveResult($result);
      } catch (Exception $ex) {
        $result = $this->didReceiveException($ex);
      } catch (Throwable $ex) {
        $result = $this->didReceiveException($ex);
      }

      $this->setResult($result);

      return true;
    }

    return false;
  }

  public function getReadSockets() {
    return $this->getProxiedFuture()->getReadSockets();
  }

  public function getWriteSockets() {
    return $this->getProxiedFuture()->getWriteSockets();
  }

  public function start() {
    $this->getProxiedFuture()->start();
    return $this;
  }

  protected function getServiceProfilerStartParameters() {
    return $this->getProxiedFuture()->getServiceProfilerStartParameters();
  }

  protected function getServiceProfilerResultParameters() {
    return $this->getProxiedFuture()->getServiceProfilerResultParameters();
  }

  abstract protected function didReceiveResult($result);

  protected function didReceiveException($exception) {
    throw $exception;
  }

}
