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

  protected function obtainConfiguredDefaultRootBranch($root_branch) {
    $config = $this->getFlowConfigurationManager();
    $configured_root = $config->getConfigValue('default.root');
    while (empty($root_branch)) {
      if (!empty($configured_root)) {
        return $configured_root;
      }
      $root_branch = phutil_console_prompt(
        pht('Please specify a root branch to use for this operation:'));
    }
    if ($root_branch !== $configured_root) {
      $confirm = phutil_console_confirm(
        pht('Would you like to use %s as the default root branch for your workspace? '.
            'All `flow` operations involving a root branch will use this branch by default.',
            phutil_console_format('**%s**', $root_branch)));
      if ($confirm) {
        $config->setConfigValue('default.root', $root_branch);
      }
    }
    return $root_branch;
  }

}
