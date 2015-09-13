<?php

/**
 * Upgrade arcanist itself.
 */
final class ArcanistUpgradeWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'upgrade';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **upgrade**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: cli
          Upgrade arcanist and libphutil to the latest versions.
EOTEXT
      );
  }

  public function run() {
    $roots = array(
      'libphutil' => dirname(phutil_get_library_root('phutil')),
      'arcanist' => dirname(phutil_get_library_root('arcanist')),
    );

    foreach ($roots as $lib => $root) {
      echo phutil_console_format(
        "%s\n",
        pht('Upgrading %s...', $lib));

      $working_copy = ArcanistWorkingCopyIdentity::newFromPath($root);
      $configuration_manager = clone $this->getConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);
      $repository = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
        $configuration_manager);

      if (!Filesystem::pathExists($repository->getMetadataPath())) {
        throw new ArcanistUsageException(
          pht(
            "%s must be in its git working copy to be automatically upgraded. ".
            "This copy of %s (in '%s') is not in a git working copy.",
            $lib,
            $lib,
            $root));
      }

      $this->setRepositoryAPI($repository);

      $this->requireCleanWorkingCopy();

      $branch_name = $repository->getBranchName();
      if ($branch_name != 'master' && $branch_name != 'stable') {
        throw new ArcanistUsageException(
          pht(
            "%s must be on either branch '%s' or '%s' to be automatically ".
            "upgraded. ".
            "This copy of %s (in '%s') is on branch '%s'.",
            $lib,
            'master',
            'stable',
            $lib,
            $root,
            $branch_name));
      }

      chdir($root);
      try {
        phutil_passthru('git pull --rebase');
      } catch (Exception $ex) {
        phutil_passthru('git rebase --abort');
        throw $ex;
      }
    }

    echo phutil_console_format(
      "**%s** %s\n",
      pht('Updated!'),
      pht('Your copy of arc is now up to date.'));
    return 0;
  }

}
