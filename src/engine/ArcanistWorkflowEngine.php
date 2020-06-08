<?php

abstract class ArcanistWorkflowEngine
  extends Phobject {

  private $workflow;
  private $viewer;
  private $logEngine;
  private $repositoryAPI;

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  final public function getWorkflow() {
    return $this->workflow;
  }

  final public function setRepositoryAPI(
    ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  final public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  final public function setLogEngine(ArcanistLogEngine $log_engine) {
    $this->logEngine = $log_engine;
    return $this;
  }

  final public function getLogEngine() {
    return $this->logEngine;
  }

}
