<?php

final class ConduitSearchFuture
  extends FutureAgent {

  private $conduitEngine;
  private $method;
  private $constraints;
  private $attachments;

  private $objects = array();
  private $cursor;

  public function setConduitEngine(ArcanistConduitEngine $conduit_engine) {
    $this->conduitEngine = $conduit_engine;
    return $this;
  }

  public function getConduitEngine() {
    return $this->conduitEngine;
  }

  public function setMethod($method) {
    $this->method = $method;
    return $this;
  }

  public function getMethod() {
    return $this->method;
  }

  public function setConstraints(array $constraints) {
    $this->constraints = $constraints;
    return $this;
  }

  public function getConstraints() {
    return $this->constraints;
  }

  public function setAttachments(array $attachments) {
    $this->attachments = $attachments;
    return $this;
  }

  public function getAttachments() {
    return $this->attachments;
  }

  public function isReady() {
    if ($this->hasResult()) {
      return true;
    }

    $futures = $this->getFutures();
    $future = head($futures);

    if (!$future) {
      $future = $this->newFuture();
    }

    if (!$future->isReady()) {
      $this->setFutures(array($future));
      return false;
    } else {
      $this->setFutures(array());
    }

    $result = $future->resolve();

    foreach ($this->readResults($result) as $object) {
      $this->objects[] = $object;
    }

    $cursor = idxv($result, array('cursor', 'after'));

    if ($cursor === null) {
      $this->setResult($this->objects);
      return true;
    }

    $this->cursor = $cursor;
    $future = $this->newFuture();
    $this->setFutures(array($future));

    return false;
  }

  private function newFuture() {
    $engine = $this->getConduitEngine();

    $method = $this->getMethod();
    $constraints = $this->getConstraints();

    $parameters = array(
      'constraints' => $constraints,
    );

    if ($this->attachments) {
      $parameters['attachments'] = $this->attachments;
    }

    if ($this->cursor !== null) {
      $parameters['after'] = (string)$this->cursor;
    }

    $conduit_future = $engine->newFuture($method, $parameters);

    return $conduit_future;
  }

  private function readResults(array $data) {
    return idx($data, 'data');
  }

}
