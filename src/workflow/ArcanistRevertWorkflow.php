<?php

/**
 * Redirects to `arc backout` workflow.
 */
final class ArcanistRevertWorkflow extends ArcanistWorkflow {

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

  public function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

  public function run() {
    echo pht(
      'Please use `%s` instead.',
      'arc backout')."\n";
    return 1;
  }

}
