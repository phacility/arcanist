<?php

/**
 * Alias for arc feature
 *
 * @group workflow
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

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException(
        'arc branch is only supported under Git.');
    }
    return parent::run();
  }

}
