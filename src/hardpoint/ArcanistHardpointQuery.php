<?php

abstract class ArcanistHardpointQuery
  extends Phobject {

  private $hardpointEngine;

  final public function setHardpointEngine(ArcanistHardpointEngine $engine) {
    $this->hardpointEngine = $engine;
    return $this;
  }

  final public function getHardpointEngine() {
    return $this->hardpointEngine;
  }

  abstract public function getHardpoints();
  abstract public function canLoadObject(ArcanistHardpointObject $object);
  abstract public function loadHardpoint(array $objects, $hardpoint);

  final protected function yieldFuture(Future $future) {
    return $this->yieldFutures(array($future))
      ->setSendResult(true);
  }

  final protected function yieldFutures(array $futures) {
    return ArcanistHardpointFutureList::newFromFutures($futures);
  }

  final protected function yieldRequests(array $objects, $requests) {
    $engine = $this->getHardpointEngine();
    $requests = $engine->requestHardpoints($objects, $requests);
    return $requests;
  }

}
