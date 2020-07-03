<?php

abstract class ArcanistRuntimeHardpointQuery
  extends ArcanistHardpointQuery {

  private $runtime;
  private $canLoadHardpoint;

  final public function setRuntime(ArcanistRuntime $runtime) {
    $this->runtime = $runtime;
    return $this;
  }

  final public function getRuntime() {
    return $this->runtime;
  }

  final public function getWorkingCopy() {
    return $this->getRuntime()->getWorkingCopy();
  }

  final public function getRepositoryAPI() {
    return $this->getWorkingCopy()->getRepositoryAPI();
  }

  public static function getAllQueries() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  final public function canLoadObject(ArcanistHardpointObject $object) {
    if ($this->canLoadHardpoint === null) {
      $this->canLoadHardpoint = $this->canLoadHardpoint();
    }

    if (!$this->canLoadHardpoint) {
      return false;
    }

    if (!$object instanceof ArcanistRef) {
      return false;
    }

    return $this->canLoadRef($object);
  }

  protected function canLoadHardpoint() {
    return true;
  }

  abstract protected function canLoadRef(ArcanistRef $ref);

  final public function newConduitSearch(
    $method,
    $constraints,
    $attachments = array()) {

    $conduit_engine = $this->getRuntime()
      ->getConduitEngine();

    $conduit_future = id(new ConduitSearchFuture())
      ->setConduitEngine($conduit_engine)
      ->setMethod($method)
      ->setConstraints($constraints)
      ->setAttachments($attachments);

    return $conduit_future;
  }

  final public function yieldConduitSearch($method, $constraints) {
    $conduit_future = $this->newConduitSearch($method, $constraints);
    return $this->yieldFuture($conduit_future);
  }

  final public function newConduit($method, $parameters) {
    $conduit_engine = $this->getRuntime()
      ->getConduitEngine();

    $call_future = $conduit_engine->newFuture($method, $parameters);

    return $call_future;
  }

  final public function yieldConduit($method, array $parameters) {
    $call_future = $this->newConduit($method, $parameters);
    return $this->yieldFuture($call_future);
  }

  final public function yieldRepositoryRef() {
    // TODO: This should probably move to Runtime.

    $runtime = $this->getRuntime();
    $workflow = $runtime->getCurrentWorkflow();

    // TODO: This is currently a blocking request, but should yield to the
    // hardpoint engine in the future.

    $repository_ref = $workflow->getRepositoryRef();
    $ref_future = new ImmediateFuture($repository_ref);

    return $this->yieldFuture($ref_future);
  }

  final public function yieldValue(array $refs, $value) {
    assert_instances_of($refs, 'ArcanistRef');

    $keys = array_keys($refs);
    $map = array_fill_keys($keys, $value);
    return $this->yieldMap($map);
  }

  final public function yieldMap(array $map) {
    return new ArcanistHardpointTaskResult($map);
  }

}
