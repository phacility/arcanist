<?php

final class ICFlowConfiguration extends ArcanistConfiguration {

  public function buildAllWorkflows() {
    $flow_workflows = id(new PhutilClassMapQuery())
      ->setAncestorClass('ICFlowBaseWorkflow')
      ->setUniqueMethod('getFlowWorkflowName')
      ->execute();

    foreach ($flow_workflows as $workflow) {
      $workflow->setIsFlowBinary(true);
    }

    $arc_workflows = id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistWorkflow')
      ->setUniqueMethod('getWorkflowName')
      ->execute();

    $arc_workflows = array_select_keys($arc_workflows, array(
      'diff',
      'land',
      'patch',
      'lint',
      'unit',
      'close-revision',
    ));
    $workflows = array_merge($flow_workflows, $arc_workflows);
    return $workflows;
  }
}
