<?php

/**
 * Alias for `arc feature`.
 */
final class ArcanistBranchWorkflow extends ArcanistFeatureWorkflow {

  public function getWorkflowName() {
    return 'branch';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **branch** [__options__]
      **branch** __name__ [__start__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git
          Alias for arc feature.
EOTEXT
      );
  }

  public function getSupportedRevisionControlSystems() {
    return array('git');
  }

}
