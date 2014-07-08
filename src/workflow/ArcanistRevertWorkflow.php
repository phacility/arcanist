<?php

/**
 * Redirects to `arc backout` workflow.
 */
final class ArcanistRevertWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'revert';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **revert**
EOTEXT
    );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
    Please use arc backout instead
EOTEXT
    );
  }

  public function getArguments() {
    return array(
      '*' => 'input',
    );
  }

  public function run() {
    $console = PhutilConsole::getConsole();
    $console->writeOut("Please use arc backout instead.\n");
  }

}
