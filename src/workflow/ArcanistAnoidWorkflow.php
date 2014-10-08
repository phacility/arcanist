<?php

final class ArcanistAnoidWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'anoid';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **anoid**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          There's only one way to find out...
EOTEXT
      );
  }

  public function run() {
    phutil_passthru(
      '%s/scripts/breakout.py',
      dirname(phutil_get_library_root('arcanist')));
  }

}
