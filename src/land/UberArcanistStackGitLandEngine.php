<?php

final class UberArcanistStackGitLandEngine
  extends ArcanistGitLandEngine {

  private $revisionIdsInStackOrder;
  private $mergedRef;

  /**
   * @return mixed
   */
  public function getRevisionIdsInStackOrder() {
    return $this->revisionIdsInStackOrder;
  }

  /**
   * @param mixed $revisionIdsInStackOrder
   */
  public function setRevisionIdsInStackOrder($revisions) {
    $this->revisionIdsInStackOrder = $revisions;
    return $this;
  }

  /**
   * Helper method to allow running child workflow
   * @param $workflow Workflow Name
   * @param $params Arguments for workflow
   * @param $err_title Error Title to be displayed
   * @param $err_msg Error Message to be displayed
   * @throws Exception
   */
  private function runChildWorkflow($workflow, $params, $err_title, $err_msg) {
    try {
      $flow = $this->getWorkflow()->buildChildWorkflow($workflow, $params);
      $err = $flow->run();
      if ($err) {
        $this->writeInfo($err_title, $err_msg.$err);
        throw new ArcanistUserAbortException();
      }
    } catch (Exception $exp) {
      echo pht("Failed executing workflow %s with args (%s).\n", $workflow,
               implode(',', $params));
      throw $exp;
    }
  }

  /**
   * Create a branch with base-revision corresponding to the passed argument
   * @param $base_revision
   * @return null|string
   */
  private function createBranch($base_revision) {
    $repository_api = $this->getRepositoryAPI();
    $repository_api->reloadWorkingCopy();
    $branch_name = $this->getBranchName();
    if ($base_revision) {
      $base_revision = $repository_api
        ->getCanonicalRevisionName($base_revision);
      $repository_api
        ->execxLocal('checkout -b %s %s', $branch_name, $base_revision);
    } else {
      $repository_api->execxLocal('checkout -b %s', $branch_name);
    }
    $repository_api->reloadWorkingCopy();
    return $branch_name;
  }

  /**
   * Create a temporary branch name
   * @return null|string
   * @throws Exception
   */
  private function getBranchName() {
    $base_name      = 'arcstack';
    $repository_api = $this->getRepositoryAPI();
    // Try 100 different branch names before giving up.
    for ($i = 0; $i < 100; $i++) {
      $proposed_name = $base_name.$i;

      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $proposed_name);

      // no error means git rev-parse found a branch
      if ($err) {
        return $proposed_name;
      }
    }

    throw new Exception(
      pht(
        'Arc was unable to automagically make a name for this patch. '.
        'Please clean up your working copy and try again.'));
  }

  protected function updateWorkingCopy() {
    $api = $this->getRepositoryAPI();
    $tempBranch = $this->createBranch($this->getTargetOnto());
    // apply changes from phabricator
    foreach ($this->revisionIdsInStackOrder as $revision) {
      $patch_args = array(
        '--revision',
        $revision,
        '--nobranch',
        '--skip-dependencies',
      );
      $this->runChildWorkflow(
        'patch',
        $patch_args,
        pht('Patching revision D%s', $revision), 'Unable to patch revision');
    }

    try {
      // if repo is ok with squashing then make simple merge
      $api->execxLocal(
        'merge --no-stat --no-commit --ff -- %s',
        $tempBranch);
    } catch (Exception $ex) {
      $api->execManualLocal('merge --abort');
      $api->execManualLocal('reset --hard HEAD --');

      throw new Exception(
        pht(
          'Local "%s" does not merge cleanly into "%s". Merge or rebase '.
          'local changes so they can merge cleanly.',
          $tempBranch,
          $this->getTargetFullRef()));
    }

    $this->getWorkflow()->didCommitMerge();

    list($stdout) = $api->execxLocal(
      'rev-parse --verify %s',
      'HEAD');
    $this->mergedRef = trim($stdout);
    // delete temporary stack if merge and everything is fin
    $api->execxLocal('checkout %s', $this->getSourceRef());
    $api->execxLocal('branch -D %s', $tempBranch);
  }

  protected function pushChange() {
    $api = $this->getRepositoryAPI();

    $this->writeInfo(
      pht('PUSHING'),
      pht('Pushing changes to "%s".', $this->getTargetFullRef()));

    $err = $api->execPassthru(
      'push -- %s %s:%s',
      $this->getTargetRemote(),
      $this->mergedRef,
      $this->getTargetOnto());

    if ($err) {
      throw new ArcanistUsageException(
        pht(
          'Push failed! Fix the error and run "%s" again.',
          'arc land'));
    }
  }
}
