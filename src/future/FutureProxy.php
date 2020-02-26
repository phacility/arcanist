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
    if ($this->hasResult()) {
      return true;
    }

    $proxied = $this->getProxiedFuture();

    $is_ready = $proxied->isReady();

    if ($proxied->hasResult()) {
      $result = $proxied->getResult();
      $result = $this->didReceiveResult($result);
      $this->setResult($result);
    }

    return $is_ready;
  }

  public function resolve() {
    $this->getProxiedFuture()->resolve();
    $this->isReady();
    return $this->getResult();
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

  abstract protected function didReceiveResult($result);

}
