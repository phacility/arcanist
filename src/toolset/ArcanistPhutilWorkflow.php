<?php

final class ArcanistPhutilWorkflow extends PhutilArgumentWorkflow {

  private $workflow;

  public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function isExecutable() {
    return true;
  }

  public function execute(PhutilArgumentParser $args) {
    return $this->getWorkflow()->executeWorkflow($args);
  }

}
