<?php

final class PhutilHelpArgumentWorkflow extends PhutilArgumentWorkflow {

  private $workflow;

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
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

    if (!$with) {
      // TODO: Update this to use a pager, too.

      $args->printHelpAndExit();
    } else {
      $out = array();
      foreach ($with as $thing) {
        $out[] = phutil_console_format(
          "**%s**\n\n",
          pht('%s WORKFLOW', strtoupper($thing)));
        $out[] = $args->renderWorkflowHelp($thing, $show_flags = true);
        $out[] = "\n";
      }
      $out = implode('', $out);

      $workflow = $this->getWorkflow();
      if ($workflow) {
        $workflow->writeToPager($out);
      } else {
        echo $out;
      }
    }
  }

}
