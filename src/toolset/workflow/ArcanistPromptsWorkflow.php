<?php

final class ArcanistPromptsWorkflow
  extends ArcanistWorkflow {

  public function supportsToolset(ArcanistToolset $toolset) {
    return true;
  }

  public function getWorkflowName() {
    return 'prompts';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Show information about prompts a workflow may execute, and review saved
responses.

**Show Prompts**

To show possible prompts a workflow may execute, run:

  $ arc prompts __workflow__

**Saving Responses**

If you always want to answer a particular prompt in a certain way, you can
save your response to the prompt. When you encounter the prompt again, your
saved response will be used automatically.

To save a response, add "*" or "!" to the end of the response you want to save
when you answer the prompt:

  - Using "*" will save the response in user configuration. In the future,
    the saved answer will be used any time you encounter the prompt (in any
    project).
  - Using "!" will save the response in working copy configuration. In the
    future, the saved answer will be used when you encounter the prompt in
    the current working copy.

For example, if you would like to always answer "y" to a particular prompt,
respond with "y*" or "y!" to save your response.

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
        pht('This workflow does not have any prompts.'));
      return 0;
    }

    $prompts = msort($prompts, 'getKey');

    $blocks = array();
    foreach ($prompts as $prompt) {
      $block = array();
      $block[] = tsprintf(
        "<bg:green>** %s **</bg> **%s**\n\n",
        pht('PROMPT'),
        $prompt->getKey());
      $block[] = tsprintf(
        "%W\n",
        $prompt->getDescription());

      $responses = $this->getSavedResponses($prompt->getKey());
      if ($responses) {
        $block[] = tsprintf("\n");
        foreach ($responses as $response) {
          $block[] = tsprintf(
            "    <bg:cyan>** > **</bg> %s\n",
            pht(
              'You have saved the response "%s" to this prompt.',
              $response->getResponse()));
        }
      }

      $blocks[] = $block;
    }

    echo tsprintf('%B', phutil_glue($blocks, tsprintf("\n")));

    return 0;
  }

  private function getSavedResponses($prompt_key) {
    $config_key = ArcanistArcConfigurationEngineExtension::KEY_PROMPTS;
    $config = $this->getConfig($config_key);

    $responses = array();
    foreach ($config as $response) {
      if ($response->getPrompt() === $prompt_key) {
        $responses[] = $response;
      }
    }

    return $responses;
  }

}
