<?php

final class FuturePool
  extends Phobject {

  private $shouldRewind;
  private $iteratorTemplate;
  private $iterator;
  private $futures = array();

  public function __construct() {
    $this->iteratorTemplate = new FutureIterator(array());
  }

  public function getIteratorTemplate() {
    return $this->iteratorTemplate;
  }

  public function addFuture(Future $future) {
    $future_key = $future->getFutureKey();

    if (!isset($this->futures[$future_key])) {
      if (!$this->iterator) {
        $this->iterator = clone $this->getIteratorTemplate();
        $this->shouldRewind = true;
      }

      $iterator = $this->iterator;
      $iterator->addFuture($future);

      $this->futures[$future_key] = $future;
    }

    return $this;
  }

  public function getFutures() {
    return $this->futures;
  }

  public function hasFutures() {
    return (bool)$this->futures;
  }

  public function resolve() {
    $iterator = $this->iterator;

    if (!$iterator) {
      return null;
    }

    if ($this->shouldRewind) {
      $iterator->rewind();
      $this->shouldRewind = false;
    } else {
      $iterator->next();
    }

    if ($iterator->valid()) {
      $future_key = $iterator->key();
      if ($future_key !== null) {
        unset($this->futures[$future_key]);
      }
      return $iterator->current();
    } else {
      $this->iterator = null;
      return null;
    }
  }

}
