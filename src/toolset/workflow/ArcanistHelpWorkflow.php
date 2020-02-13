<?php

final class ArcanistHelpWorkflow
  extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'help';
  }

  public function newPhutilWorkflow() {
    return new PhutilHelpArgumentWorkflow();
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

}
