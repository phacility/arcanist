<?php

final class PhutilHelpArgumentWorkflow extends PhutilArgumentWorkflow {

  private $runtime;

  public function setRuntime($runtime) {
    $this->runtime = $runtime;
    return $this;
  }

  public function getRuntime() {
    return $this->runtime;
  }

  protected function didConstruct() {
    $this->setName('help');
    $this->setExamples(<<<EOHELP
**help** [__command__]
EOHELP
);
    $this->setSynopsis(<<<EOHELP
Show this help, or workflow help for __command__.
EOHELP
      );
    $this->setArguments(
      array(
        array(
          'name'      => 'help-with-what',
          'wildcard'  => true,
        ),
      ));
  }

  public function isExecutable() {
    return true;
  }

  public function execute(PhutilArgumentParser $args) {
    $with = $args->getArg('help-with-what');

    $runtime = $this->getRuntime();
    $toolset = $runtime->getToolset();
    if ($toolset->getToolsetKey() === 'arc') {
      $workflows = $args->getWorkflows();

      $legacy = array();

      $legacy[] = new ArcanistCloseRevisionWorkflow();
      $legacy[] = new ArcanistCommitWorkflow();
      $legacy[] = new ArcanistCoverWorkflow();
      $legacy[] = new ArcanistDiffWorkflow();
      $legacy[] = new ArcanistExportWorkflow();
      $legacy[] = new ArcanistGetConfigWorkflow();
      $legacy[] = new ArcanistSetConfigWorkflow();
      $legacy[] = new ArcanistInstallCertificateWorkflow();
      $legacy[] = new ArcanistLandWorkflow();
      $legacy[] = new ArcanistLintersWorkflow();
      $legacy[] = new ArcanistLintWorkflow();
      $legacy[] = new ArcanistListWorkflow();
      $legacy[] = new ArcanistPatchWorkflow();
      $legacy[] = new ArcanistPasteWorkflow();
      $legacy[] = new ArcanistTasksWorkflow();
      $legacy[] = new ArcanistTodoWorkflow();
      $legacy[] = new ArcanistUnitWorkflow();
      $legacy[] = new ArcanistWhichWorkflow();

      foreach ($legacy as $workflow) {
        // If this workflow has been updated but not removed from the list
        // above yet, just skip it.
        if ($workflow instanceof ArcanistArcWorkflow) {
          continue;
        }

        $workflows[] = $workflow->newLegacyPhutilWorkflow();
      }

      $args->setWorkflows($workflows);
    }

    if (!$with) {
      $args->printHelpAndExit();
    } else {
      foreach ($with as $thing) {
        echo phutil_console_format(
          "**%s**\n\n",
          pht('%s WORKFLOW', strtoupper($thing)));
        echo $args->renderWorkflowHelp($thing, $show_flags = true);
        echo "\n";
      }
      exit(PhutilArgumentParser::PARSE_ERROR_CODE);
    }
  }

}
