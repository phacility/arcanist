<?php

final class ArcanistHelpWorkflow
  extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'help';
  }

  public function newPhutilWorkflow() {
    return id(new PhutilHelpArgumentWorkflow())
      ->setRuntime($this->getRuntime());
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

}
