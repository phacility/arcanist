<?php

/**
 * @group workflow
 */
final class ArcanistMarkCommittedWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'mark-committed';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **mark-committed** (DEPRECATED)
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Deprecated. Moved to "close-revision".
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'deprecated',
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {
    throw new ArcanistUsageException(
      "'arc mark-committed' is now 'arc close-revision' (because ".
      "'mark-committed' only really made sense under SVN).");
  }
}
