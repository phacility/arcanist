<?php

/**
 * Alias for `arc feature`.
 */
final class ArcanistBookmarkWorkflow extends ArcanistFeatureWorkflow {

  public function getWorkflowName() {
    return 'bookmark';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **bookmark** [__options__]
      **bookmark** __name__ [__start__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: hg
          Alias for arc feature.
EOTEXT
      );
  }

  public function getSupportedRevisionControlSystems() {
    return array('hg');
  }

}
