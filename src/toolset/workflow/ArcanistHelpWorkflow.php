<?php

final class ArcanistHelpWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'help';
  }

  public function newPhutilWorkflow() {
    return new PhutilHelpArgumentWorkflow();
  }

}
