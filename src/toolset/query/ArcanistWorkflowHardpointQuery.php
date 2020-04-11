<?php

abstract class ArcanistWorkflowHardpointQuery
  extends ArcanistHardpointQuery {

  private $workflow;
  private $canLoadHardpoint;

  final public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  final public function getWorkflow() {
    return $this->workflow;
  }

  final public function getWorkingCopy() {
    return $this->getWorkflow()->getWorkingCopy();
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

  final public function yieldConduit($method, array $parameters) {
    $conduit_engine = $this->getWorkflow()
      ->getConduitEngine();

    $call_object = $conduit_engine->newCall($method, $parameters);
    $call_future = $conduit_engine->newFuture($call_object);

    return $this->yieldFuture($call_future);
  }

  final public function yieldRepositoryRef() {
    $workflow = $this->getWorkflow();

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
