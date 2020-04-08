<?php

final class ArcanistUpgradeWorkflow
  extends ArcanistArcWorkflow {

  public function getWorkflowName() {
    return 'upgrade';
  }

  public function getWorkflowInformation() {
    $help = pht(<<<EOTEXT
Upgrade Arcanist to the latest version.
EOTEXT
);

    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Upgrade Arcanist to the latest version.'))
      ->addExample(pht('**upgrade**'))
      ->setHelp($help);
  }

  public function getWorkflowArguments() {
    return array();
  }

  public function runWorkflow() {
    $log = $this->getLogEngine();

    $roots = array(
      'arcanist' => dirname(phutil_get_library_root('arcanist')),
    );

    $supported_branches = array(
      'master',
      'stable',
    );
    $supported_branches = array_fuse($supported_branches);

    foreach ($roots as $library => $root) {
      $log->writeStatus(
        pht('PREPARING'),
        pht(
          'Preparing to upgrade "%s"...',
          $library));

      $working_copy = ArcanistWorkingCopy::newFromWorkingDirectory($root);

      $repository_api = $working_copy->getRepositoryAPI();
      $is_git = ($repository_api instanceof ArcanistGitAPI);

      if (!$is_git) {
        throw new PhutilArgumentUsageException(
          pht(
            'The "arc upgrade" workflow uses "git pull" to upgrade '.
            'Arcanist, but the "arcanist/" directory  (in "%s") is not a Git '.
            'working copy. You must leave "arcanist/" as a Git '.
            'working copy to use "arc upgrade".',
            $root));
      }

      // NOTE: Don't use requireCleanWorkingCopy() here because it tries to
      // amend changes and generally move the workflow forward. We just want to
      // abort if there are local changes and make the user sort things out.
      $uncommitted = $repository_api->getUncommittedStatus();
      if ($uncommitted) {
        $message = pht(
          'You have uncommitted changes in the working copy ("%s") for this '.
          'library ("%s"):',
          $root,
          $library);

        $list = id(new PhutilConsoleList())
          ->setWrap(false)
          ->addItems(array_keys($uncommitted));

        id(new PhutilConsoleBlock())
          ->addParagraph($message)
          ->addList($list)
          ->addParagraph(
            pht(
              'Discard these changes before running "arc upgrade".'))
          ->draw();

        throw new PhutilArgumentUsageException(
          pht('"arc upgrade" can only upgrade clean working copies.'));
      }

      $branch_name = $repository_api->getBranchName();
      if (!isset($supported_branches[$branch_name])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Library "%s" (in "%s") is on branch "%s", but this branch is '.
            'not supported for automatic upgrades. Supported branches are: '.
            '%s.',
            $library,
            $root,
            $branch_name,
            implode(', ', array_keys($supported_branches))));
      }

      $log->writeStatus(
        pht('UPGRADING'),
        pht(
          'Upgrading "%s" (on branch "%s").',
          $library,
          $branch_name));

      $command = csprintf(
        'git pull --rebase origin -- %R',
        $branch_name);

      $future = (new PhutilExecPassthru($command))
        ->setCWD($root);

      try {
        $this->newCommand($future)
          ->execute();
      } catch (Exception $ex) {
        // If we failed, try to go back to the old state, then throw the
        // original exception.
        exec_manual('git rebase --abort');
        throw $ex;
      }
    }

    $log->writeSuccess(
      pht('UPGRADED'),
      pht('Your copy of Arcanist is now up to date.'));

    return 0;
  }

}
