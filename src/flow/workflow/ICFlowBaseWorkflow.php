<?php

abstract class ICFlowBaseWorkflow extends ICArcanistWorkflow {

  private $isFlowBinary = false;

  final public function setIsFlowBinary($is_flow_binary) {
    $this->isFlowBinary = $is_flow_binary;
    return $this;
  }

  final public function getIsFlowBinary() {
    return $this->isFlowBinary;
  }

  final public function getWorkflowName() {
    if ($this->getIsFlowBinary()) {
      return $this->getFlowWorkflowName();
    }
    return $this->getArcanistWorkflowName();
  }

  abstract public function getWorkflowBaseName();

  public function getArcanistWorkflowName() {
    return 'flow-'.$this->getFlowWorkflowName();
  }

  public function getFlowWorkflowName() {
    return $this->getWorkflowBaseName();
  }
}
