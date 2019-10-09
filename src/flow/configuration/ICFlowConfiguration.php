<?php

final class ICFlowConfiguration extends ArcanistConfiguration {

  private static $forbidden_workflows = [
    // Workflows that should not be called directly from the command line
    'patch',
  ];

  public function buildAllWorkflows() {
    $atg_workflows = id(new PhutilClassMapQuery())
      ->setAncestorClass('ICFlowBaseWorkflow')
      ->setUniqueMethod('getFlowWorkflowName')
      ->execute();
    
    foreach ($atg_workflows as $workflow) {
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
    $workflows = array_merge($atg_workflows, $arc_workflows);
    return $workflows;
  }

  public function raiseUnknownAsdfCommand($command) {
    // TODO: Add in suggestions from PhutilComplete
    $message = pht("Unknown command '%s'. Try '%s'.", $command, 'asdf help');
    throw new ArcanistUsageException($message);
  }

  /**
   * Prevent ATG engineers from accessing workflows from the CLI
   * that run the risk of inducing unsupported graph states.
   * 
   * Throws ArcanistUsageException
   * 
   * @return void
   */
  final public function preventDangerousWorkflows($command) {
    if (in_array($command, self::$forbidden_workflows)) {
      throw new ArcanistUsageException(phutil_console_format(pht(
        "The **%s** workflow is not supported in Asdf.\n",
        $command)));
    }
  }

}
