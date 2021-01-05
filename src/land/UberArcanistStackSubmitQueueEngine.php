<?php
final class UberArcanistStackSubmitQueueEngine
  extends UberArcanistSubmitQueueEngine
{

  private $revisionIdsInStackOrder;
  private $revisionIdToDiffIds;
  private $directPatchApplyBranches;
  private $rebasePatchApplyBranches;
  private $directPatchDiffContainer;
  private $traceModeEnabled;
  private $tempBranch;
  private $rebaseCheckEnabled;
  private $cleanupDone;

  /**
   * @return mixed
   */
  public function getRevisionIdsInStackOrder()
  {
    return $this->revisionIdsInStackOrder;
  }

  /**
   * @param mixed $revisionIdsInStackOrder
   */
  public function setRevisionIdsInStackOrder($revisionIdsInStackOrder)
  {
    $this->revisionIdsInStackOrder = $revisionIdsInStackOrder;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getTraceModeEnabled()
  {
    return $this->traceModeEnabled;
  }

  /**
   * @param mixed $traceModeEnabled
   */
  public function setTraceModeEnabled($traceModeEnabled)
  {
    $this->traceModeEnabled = $traceModeEnabled;
    return $this;
  }

  /**
   * @param mixed $rebaseCheckEnabled
   */
  public function setRebaseCheckEnabled($rebaseCheckEnabled)
  {
    $this->rebaseCheckEnabled = $rebaseCheckEnabled;
    return $this;
  }

  /**
   * Cleanup temporary branches created for validations
   */
  private function cleanup() {
    $console = PhutilConsole::getConsole();
    if (!$this->cleanupDone) {
      $console->writeOut("**<bg:blue> %s </bg>** %s\n", 'Cleaning up temp branches', pht(""));

      $api = $this->getRepositoryAPI();
      // Go to parent branch.
      $api->execxLocal('checkout %s', $this->getTargetOnto());
      $api->reloadWorkingCopy();
      $this->cleanupTemporaryBranches($this->rebasePatchApplyBranches);
      $this->cleanupTemporaryBranches($this->directPatchApplyBranches);
      $tempArray = array("tmp" => $this->tempBranch);
      $this->cleanupTemporaryBranches($tempArray);
      $console->writeOut("**<bg:green> %s </bg>** %s\n", 'Finished Cleaning up temp branches', pht(""));
      $this->cleanupDone = true;
    } else {
      $console->writeOut("**<bg:yellow> %s </bg>** %s\n", 'Cleaning already done. skipping', pht(""));
    }
  }

  private function cleanupTemporaryBranches(&$localBranches) {
    $api = $this->getRepositoryAPI();
    if (!empty($localBranches)) {
      foreach ($localBranches as $revision => $branch) {
        $this->debugLog("Deleting temporary branch %s\n", $branch);
        try {
          $api->execxLocal('branch -D -- %s', $branch);
        } catch (Exception $ex) {
          $this->writeInfo("ARC_CLEANUP_ERROR",
            pht("Unable to remove temporary branch %s failed with error.code=", $branch), $ex);
        }
      }
    }
  }

  /**
   * Stores the diff between base and head commit in the current branch.
   * @param $repository_api
   * @param $revision_id
   * @param $local_branch
   */
  private function copyDiff($repository_api, $revision_id, $local_branch) {
    $diff = $repository_api->getFullGitDiff($repository_api->getBaseCommit(), $repository_api->getHeadCommit());
    if (!empty($diff)) {
      $diff = $this->normalizeDiff($diff);
    }
    $this->debugLog("Adding diff for revision D%s (Base : %s, Head: %s). Branch : %s, Diff : (%s)\n",
      $revision_id, $repository_api->getBaseCommit(), $repository_api->getHeadCommit(), $local_branch, $diff);
    $this->directPatchDiffContainer[$revision_id] = $diff;

  }

  /**
   * Arc patches (without nobranch to avoid cherry-pick errors) each revision in the stack
   * Create another copy of the branch to do rebase check.
   */
  private function setupBranches() {
    $this->cleanupDone = false;
    $repository_api = $this->getRepositoryAPI();
    $base_ref =  $repository_api->getBaseCommit();
    $base_revision = $base_ref;
    $this->directPatchDiffContainer = array();
    $this->directPatchApplyBranches = array();
    $this->rebasePatchApplyBranches = array();
    // Set up branch for patching
    $this->tempBranch = $this->createBranch($base_revision);
    foreach($this->revisionIdsInStackOrder as $revision_id) {
      $diffIds = $this->revisionIdToDiffIds[$revision_id];
      $latestDiffId = head($diffIds);
      // Below command would have created a new branch
      // Run in non-interactive mode with default "no" answer so that the patch fails if it prompts for user input
      $this->runCommandSilently(array("echo", "N", "|", "arc", 'patch', "--diff", $latestDiffId,
        "--uber-use-staging-git-tags", "--uber-use-merge-strategy"));
      $repository_api->reloadWorkingCopy();
      // Set Base Commit to be HEAD-1 as arc-patch guarantees single commit-id
      $repository_api->setBaseCommit("HEAD~1");
      $branchName = $repository_api->getBranchName();
      $this->directPatchApplyBranches[$revision_id] = $branchName;
      $this->copyDiff($repository_api, $revision_id, $branchName);
      $this->rebasePatchApplyBranches[$revision_id] = $this->createBranch(null);
      $repository_api->reloadWorkingCopy();
      // Go to parent branch.
      $repository_api->execxLocal('checkout %s', $this->getTargetOnto());
      $repository_api->reloadWorkingCopy();
    }
  }


  /**
   * Query phab and collect diff ids for each revision
   * @throws ArcanistUsageException
   */
  private function buildRevisionIdToDiffIds() {
    foreach($this->revisionIdsInStackOrder as $revision_id) {
      $revisions = $this->getWorkflow()->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if (!$revisions) {
        throw new ArcanistUsageException(pht(
          "No such revision '%s'!",
          "D{$revision_id}"));
      }
      $revision = head($revisions);
      $this->revisionIdToDiffIds[$revision_id] = $revision['diffs'];
    }
  }

  private function rebase($targetBranch, $ontoBranch, $verbose) {
    $repository_api = $this->getRepositoryAPI();
    $repository_api->execxLocal('checkout %s', $targetBranch);
    $repository_api->reloadWorkingCopy();
    if ($ontoBranch != null) {
      chdir($repository_api->getPath());
      if ($verbose) {
        echo phutil_console_format(pht('Rebasing **%s** onto **%s**', $targetBranch, $ontoBranch) . "\n");
      }
      if (!$verbose) {
        try {
          $this->runCommandSilently(array('echo', 'y', '|', 'git', 'rebase', pht('%s', $ontoBranch)),
                                          !$this->getUsesArcFlow());
        } catch (Exception $e) {
          if (!$this->getUsesArcFlow()) {
            throw $e;
          }
          $this->writeInfo('ARC_REBASE',
            pht('Unable to use standard rebase, trying alternative - rebase --onto %s %s^',  $ontoBranch, $targetBranch));
          $repository_api->execxLocal('rebase --abort');
          $repository_api->reloadWorkingCopy();
          $this->runCommandSilently(array('echo', 'y', '|', 'git', 'rebase', '--onto', pht('%s', $ontoBranch), pht('%s^', $targetBranch)));
        }
      } else {
        $err = phutil_passthru('git rebase %s', $ontoBranch);
        if ($err) {
          if ($this->getUsesArcFlow()) {
            $this->writeInfo('ARC_REBASE',
              pht('Unable to use standard rebase, trying alternative - rebase --onto %s %s^',  $ontoBranch, $targetBranch));
            $repository_api->execxLocal('rebase --abort');
            $repository_api->reloadWorkingCopy();
            $err = phutil_passthru('git rebase --onto %s %s^', $ontoBranch, $targetBranch);
          }
          if ($err) {
            throw new ArcanistUsageException(pht(
              "'%s' failed. You can abort with '%s', or resolve conflicts ".
              "and use '%s' to continue forward. After resolving the rebase, ".
              "run '%s'.",
              sprintf('git rebase %s', $ontoBranch),
              'git rebase --abort',
              'git rebase --continue',
              'arc diff'));
          }
        }
      }
      $repository_api->reloadWorkingCopy();
    }
  }

  private function rebaseAndArcDiffStack($start_index) {
    $prev_index = $start_index - 1;
    // By definition, Prev Index will always be valid
    if ($prev_index >= 0) {
      throw new ArcanistUsageException('Unexpected: Starting index for '.
                                       'rebasing + arc-diff');
    }
    for ($index = $start_index;
         $index < count($this->revisionIdsInStackOrder);
         $index++) {
      $prev_diff = $this->revisionIdsInStackOrder[$prev_index];
      $curr_diff = $this->revisionIdsInStackOrder[$index];
      $this->debugLog("%s\n",
        pht('Rebasing diff D%s onto D%s and doing arc-diff for D%s',
            $curr_diff, $prev_diff, $curr_diff));
      $curr_branch = $this->rebasePatchApplyBranches[$curr_diff];
      $parent_branch = $this->rebasePatchApplyBranches[$prev_diff];
      $this->rebase($curr_branch, $parent_branch, true);
      $this->runChildWorkflow('diff',
        array('--update', pht('D%s', $curr_diff), 'HEAD^1'),
        "ARC_DIFF_ERROR",
        pht('arc diff for D%s failed with error.code=', $curr_diff));
      $prev_index = $start_index;
    }
  }

  private function runCommandSilently($cmdArr, $print_output = true) {
    $stdoutFile = tempnam("/tmp", "arc_stack_out_");
    $stderrFile = tempnam("/tmp", "arc_stack_err_");
    $cmd = null;
    try {
      // Pass default-yes (if needed) to the arc command to make it non-interactive.
      $cmdArr = array_merge($cmdArr, array(pht(">%s",$stdoutFile), pht("2>%s", $stderrFile)));
      $cmd = implode(" ", $cmdArr);
      $this->debugLog("Executing cmd (%s)\n", $cmd);
      $this->execxLocal($cmd);
    } catch (Exception $exp) {
      if ($print_output === true) {
        echo pht("Command failed (%s) Output : \n%s\nError : \n%s\n", $cmd,
          file_get_contents($stdoutFile), file_get_contents($stderrFile));
      }
      throw $exp;
    } finally {
      unlink($stderrFile);
      unlink($stdoutFile);
    }
  }

  /**
   * Helper method to allow running child workflow
   * @param $workflow Workflow Name
   * @param $paramArray Arguments for workflow
   * @param $errTitle Error Title to be displayed
   * @param $errMessage Error Message to be displayed
   * @throws Exception
   */
  private function runChildWorkflow($workflow, $paramArray, $errTitle, $errMessage) {
    try {
      $cmdWorkflow = $this->getWorkflow()->buildChildWorkflow($workflow, $paramArray);
      $err = $cmdWorkflow->run();
      if ($err) {
        $this->writeInfo($errTitle, $errMessage . $err);
        throw new ArcanistUserAbortException();
      }
    } catch (Exception $exp) {
      echo pht("Failed executing workflow %s with args (%s).\n", $workflow, implode(",", $paramArray));
      throw $exp;
    }
  }

  /**
   * Performs rebase check for each revision in the stack
   * @return int index of the first revision in stack order that failed the rebase-check
   * @throws ArcanistUserAbortException
   */
  private function ensureStackRebasedCorrectly() {
    $parentRevisionId = null;
    $repository_api = $this->getRepositoryAPI();
    $index = 0;
    $parent_branch = null;
    foreach($this->revisionIdsInStackOrder as $revision_id) {
      $repository_api->reloadWorkingCopy();
      $curr_branch = $this->rebasePatchApplyBranches[$revision_id];
      $this->rebase($curr_branch, $parent_branch, $this->traceModeEnabled);
      $repository_api->reloadWorkingCopy();
      // We are in current branch. Set Base Commit to be HEAD-1
      $repository_api->setBaseCommit("HEAD~1");
      $local_diff =  $repository_api->getFullGitDiff($repository_api->getBaseCommit(),
        $repository_api->getHeadCommit());
      if (! empty($local_diff)) {
        $local_diff = $this->normalizeDiff($local_diff);
      }
      if ($local_diff != $this->directPatchDiffContainer[$revision_id]) {
        $this->debugLog("Local Diff for revision D%s (Base : %s, Head: %s). Branch : %s, Diff : (%s)\n",
          $revision_id, $repository_api->getBaseCommit(),
          $repository_api->getHeadCommit(), $curr_branch, $local_diff);
        $this->debugLog("Direct patch diff (%s)\n", $this->directPatchDiffContainer[$revision_id]);
        $ok = phutil_console_confirm(pht(
          "IMPORTANT - Revision D%s does not seem to be based-off of latest diffId of revision D%s. ".
          "Please rebase D%s and rest of revisions in the stack. Arcanist can also try to auto rebase and arc-diff for".
          " you but this is ONLY BEST EFFORT. If there are merge-conflicts, it would exit and you may need to fix the".
          " conflicts and cleanup branches yourself. Do you still want arcanist to auto arc-diff %s and its ".
          "dependent diffs?", $revision_id, $parentRevisionId, $revision_id, $revision_id));
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
        return $index;
      }
      $parent_branch = $curr_branch;
      $parentRevisionId = $revision_id;
      $index++;
    }
    return -1;
  }

  /**
   * Main method called by execute to validate
   * Ensures each revision in the diff is rebased against latest diff of its parent.
   */
  protected function validate() {
    $untracked = $repository_api = $this->getRepositoryAPI()
      ->getUntrackedChanges();
    if ($untracked) {
      throw new ArcanistUsageException(pht(
        'Repository contains untracked changes, stash it!'));
    }
    $prevRestoreFlag = $this->restoreWhenDestroyed;
    $this->restoreWhenDestroyed = false;
    try {
      $console = PhutilConsole::getConsole();
      $console->writeOut("**<bg:blue> %s </bg>** %s\n", "VERIFY", "Starting validations !!");
      $console->writeOut("**<bg:yellow> %s </bg>** %s\n", "NOTE",
        "Temp branches are being created. If you kill the process, please make sure to cleanup the branches !!");
      $this->buildRevisionIdToDiffIds();
      if ($this->rebaseCheckEnabled) {
        $console->writeOut("**<bg:blue> %s </bg>** %s\n", "ARC_PATCH",
          pht("Checking if rebase is needed. This step could potentially take a while  !!"));
        $console->writeOut("***<bg:blue> %s </bg>*** %s\n", "ARC_PATCH",
          pht(" Applying patches to temporary branches !!"));
        $this->setupBranches();
        $console->writeOut("***<bg:blue> %s </bg>*** %s\n", "ARC_PATCH",
          pht("Finished applying patches to temporary branches !!"));
        $badIndex = $this->ensureStackRebasedCorrectly();
        $badDiff = null;
        if ($badIndex > 0) {
          // We need to auto-rebase and
          $this->rebaseAndArcDiffStack($badIndex);
          // Refresh Diff Ids as we have rebased
          $this->buildRevisionIdToDiffIds();
          $console->writeOut("***<bg:green> %s </bg>*** %s\n", "ARC_PATCH", pht("Completed rebasing and arc-diff.\n"));
        }
      } else {
        $console->writeOut("***<bg:yellow> %s </bg>*** %s\n", "REBASE_CHECK", pht("Rebase Check skipped since user requested it !!"));
      }
      $console->writeOut("**<bg:green> %s </bg>** %s\n", 'Verification Passed', pht("Ready to land"));
    } finally {
      $this->cleanup();
      $this->restoreWhenDestroyed = $prevRestoreFlag;
    }
  }

  /**
   * Called by super-class for submitting land request to SQ.
   */
  protected function pushChangeToSubmitQueue() {
    $this->writeInfo(
      pht('PUSHING'),
      pht('Pushing changes to Submit Queue.'));
    $api = $this->getRepositoryAPI();
    $remoteUrl = $api->uberGetGitRemotePushUrl($this->getTargetRemote());

    $stack = $this->generateRevisionDiffMappingForLanding();
    $statusUrl = $this->submitQueueClient->submitMergeStackRequest(
      $remoteUrl,
      $stack,
      $this->shouldShadow,
      $this->getTargetOnto());
    $this->writeInfo(
      pht('Successfully submitted the request to the Submit Queue.'),
      pht('Please use "%s" to track your changes', $statusUrl));
  }

  private function generateRevisionDiffMappingForLanding() {
    $revisonDiffStack = array();
    foreach ($this->revisionIdsInStackOrder as $revisionId) {
      array_push($revisonDiffStack, array(
        "revisionId" => $revisionId,
        "diffId" => head($this->revisionIdToDiffIds[$revisionId])
      ));
    }
    return $revisonDiffStack;
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
      $base_revision = $repository_api->getCanonicalRevisionName($base_revision);
      $repository_api->execxLocal('checkout -b %s %s', $branch_name, $base_revision);
    } else {
      $repository_api->execxLocal('checkout -b %s', $branch_name);
    }
    $this->debugLog("%s\n", pht('Created and checked out branch %s.\n', $branch_name));
    $repository_api->reloadWorkingCopy();
    return $branch_name;
  }

  /**
   * Get Landing commits for landing stack-diffs
   * @return mixed
   */
  protected function getLandingCommits() {
    $result = array();
    foreach ($this->revisionIdsInStackOrder as $revisionId) {
      $topDiffId = head($this->revisionIdToDiffIds[$revisionId]);
      $diff = head($this->getConduit()->callMethodSynchronous(
        'differential.querydiffs',
        array('ids' => array($topDiffId))));
      $properties = idx($diff, 'properties', array());
      $commits = idx($properties, 'local:commits', array());
      $result = array_merge($result, $commits);
    }
    return ipull($result, 'summary');
  }

  /**
   * Helper method to pring debug logs
   * @param array ...$message
   */
  private function debugLog(...$message) {
    if ( $this->traceModeEnabled) {
      echo phutil_console_format(call_user_func_array('pht', $message));
    }
  }

  /**
   * Helper method to execute CLI
   * @param $pattern
   * @return mixed
   */
  private function execxLocal($pattern /* , ... */) {
    $args = func_get_args();
    $future = newv('ExecFuture', $args);
    $future->setCWD($this->getRepositoryAPI()->getPath());
    return $future->resolvex();
  }

  /**
   * Create a temporary branch name
   * @return null|string
   * @throws Exception
   */
  private function getBranchName() {
    $branch_name    = null;
    $repository_api = $this->getRepositoryAPI();
    $revision_id    = idx(nonempty($this->revision, array()), 'id');
    $base_name      = 'arcstack';
    if ($revision_id) {
      $base_name .= "-D{$revision_id}_";
    }

    // Try 100 different branch names before giving up.
    for( $i = 0; $i<100; $i++ )  {
      $proposed_name = $base_name.$i;

      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $proposed_name);

      // no error means git rev-parse found a branch
      if (!$err) {
        $this->debugLog(
          "%s\n",
          pht(
            'Branch name %s already exists; trying a new name.\n',
            $proposed_name));
        continue;
      } else {
        $branch_name = $proposed_name;
        break;
      }
    }

    if (!$branch_name) {
      throw new Exception(
        pht(
          'Arc was unable to automagically make a name for this patch. '.
          'Please clean up your working copy and try again.'));
    }

    return $branch_name;
  }
}
