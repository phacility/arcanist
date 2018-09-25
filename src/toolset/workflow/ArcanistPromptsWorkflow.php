<?php

final class ArcanistPromptsWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'prompts';
  }

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Show information about prompts a workflow may execute and configure default
responses.

**Show Prompts**

To show possible prompts a workflow may execute, run:

  $ arc prompts <workflow>
EOTEXT
);

    return $this->newWorkflowInformation()
      ->addExample(pht('**prompts** __workflow__'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('argv')
        ->setWildcard(true),
    );
  }

  public function runWorkflow() {
    $argv = $this->getArgument('argv');

    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht('Provide a workflow to list prompts for.'));
    }

    $runtime = $this->getRuntime();
    $workflows = $runtime->getWorkflows();

    $workflow_key = array_shift($argv);
    $workflow = idx($workflows, $workflow_key);

    if (!$workflow) {
      throw new PhutilArgumentUsageException(
        pht(
          'Workflow "%s" is unknown. Supported workflows are: %s.',
          $workflow_key,
          implode(', ', array_keys($workflows))));
    }

    $prompts = $workflow->getPromptMap();
    if (!$prompts) {
      echo tsprintf(
        "%s\n",
        pht('This workflow can not prompt.'));
      return 0;
    }

    foreach ($prompts as $prompt) {
      echo tsprintf(
        "**%s**\n",
        $prompt->getKey());
      echo tsprintf(
        "%s\n",
        $prompt->getDescription());
    }

    return 0;
  }

}
