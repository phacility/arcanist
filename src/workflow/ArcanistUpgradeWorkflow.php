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

  public function getArguments() {
    return array();
  }

  public function run() {
    $roots = array();
    $roots['libphutil'] = dirname(phutil_get_library_root('phutil'));
    $roots['arcanist'] = dirname(phutil_get_library_root('arcanist'));

    foreach ($roots as $lib => $root) {
      echo "Upgrading {$lib}...\n";

      if (!Filesystem::pathExists($root.'/.git')) {
        throw new ArcanistUsageException(
          "{$lib} must be in its git working copy to be automatically ".
          "upgraded. This copy of {$lib} (in '{$root}') is not in a git ".
          "working copy.");
      }

      $working_copy = ArcanistWorkingCopyIdentity::newFromPath($root);

      $configuration_manager = clone $this->getConfigurationManager();
      $configuration_manager->setWorkingCopyIdentity($working_copy);
      $repository_api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
        $configuration_manager);

      $this->setRepositoryAPI($repository_api);

      // Require no local changes.
      $this->requireCleanWorkingCopy();

      // Require the library be on master.
      $branch_name = $repository_api->getBranchName();
      if ($branch_name != 'master') {
        throw new ArcanistUsageException(
          "{$lib} must be on branch 'master' to be automatically upgraded. ".
          "This copy of {$lib} (in '{$root}') is on branch '{$branch_name}'.");
      }

      chdir($root);
      try {
        phutil_passthru('git pull --rebase');
      } catch (Exception $ex) {
        phutil_passthru('git rebase --abort');
        throw $ex;
      }
    }

    echo phutil_console_wrap(
      phutil_console_format(
        "**Updated!** Your copy of arc is now up to date.\n"));

    return 0;
  }

}
