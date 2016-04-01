<?php

final class ArcanistGitLandEngine
  extends ArcanistLandEngine {

  private $localRef;
  private $localCommit;
  private $sourceCommit;
  private $mergedRef;
  private $restoreWhenDestroyed;

  public function execute() {
    $this->verifySourceAndTargetExist();
    $this->fetchTarget();

    $this->printLandingCommits();

    if ($this->getShouldPreview()) {
      $this->writeInfo(
        pht('PREVIEW'),
        pht('Completed preview of operation.'));
      return;
    }

    $this->saveLocalState();

    try {
      $this->identifyRevision();
      $this->updateWorkingCopy();

      if ($this->getShouldHold()) {
        $this->didHoldChanges();
      } else {
        $this->pushChange();
        $this->reconcileLocalState();

        $api = $this->getRepositoryAPI();
        $api->execxLocal('submodule update --init --recursive');

        if ($this->getShouldKeep()) {
          echo tsprintf(
            "%s\n",
            pht('Keeping local branch.'));
        } else {
          $this->destroyLocalBranch();
        }

        $this->writeOkay(
          pht('DONE'),
          pht('Landed changes.'));
      }

      $this->restoreWhenDestroyed = false;
    } catch (Exception $ex) {
      $this->restoreLocalState();
      throw $ex;
    }
  }

  public function __destruct() {
    if ($this->restoreWhenDestroyed) {
      $this->writeWARN(
        pht('INTERRUPTED!'),
        pht('Restoring working copy to its original state.'));

      $this->restoreLocalState();
    }
  }

  protected function getLandingCommits() {
    $api = $this->getRepositoryAPI();

    list($out) = $api->execxLocal(
      'log --oneline %s..%s --',
      $this->getTargetFullRef(),
      $this->sourceCommit);

    $out = trim($out);

    if (!strlen($out)) {
      return array();
    } else {
      return phutil_split_lines($out, false);
    }
  }

  private function identifyRevision() {
    $api = $this->getRepositoryAPI();
    $api->execxLocal('checkout %s --', $this->getSourceRef());
    call_user_func($this->getBuildMessageCallback(), $this);
  }

  private function verifySourceAndTargetExist() {
    $api = $this->getRepositoryAPI();

    list($err) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getTargetFullRef());

    if ($err) {
      throw new Exception(
        pht(
          'Branch "%s" does not exist in remote "%s".',
          $this->getTargetOnto(),
          $this->getTargetRemote()));
    }

    list($err, $stdout) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getSourceRef());

    if ($err) {
      throw new Exception(
        pht(
          'Branch "%s" does not exist in the local working copy.',
          $this->getSourceRef()));
    }

    $this->sourceCommit = trim($stdout);
  }

  private function fetchTarget() {
    $api = $this->getRepositoryAPI();

    $ref = $this->getTargetFullRef();

    $this->writeInfo(
      pht('FETCH'),
      pht('Fetching %s...', $ref));

    // NOTE: Although this output isn't hugely useful, we need to passthru
    // instead of using a subprocess here because `git fetch` may prompt the
    // user to enter a password if they're fetching over HTTP with basic
    // authentication. See T10314.

    $err = $api->execPassthru(
      'fetch --quiet -- %s %s',
      $this->getTargetRemote(),
      $this->getTargetOnto());

    if ($err) {
      throw new ArcanistUsageException(
        pht(
          'Fetch failed! Fix the error and run "%s" again.',
          'arc land'));
    }
  }

  private function updateWorkingCopy() {
    $api = $this->getRepositoryAPI();
    $source = $this->sourceCommit;

    $api->execxLocal(
      'checkout %s --',
      $this->getTargetFullRef());

    list($original_author, $original_date) = $this->getAuthorAndDate($source);

    try {
      if ($this->getShouldSquash()) {
        // NOTE: We're explicitly specifying "--ff" to override the presence
        // of "merge.ff" options in user configuration.

        $api->execxLocal(
          'merge --no-stat --no-commit --ff --squash -- %s',
          $source);
      } else {
        $api->execxLocal(
          'merge --no-stat --no-commit --no-ff -- %s',
          $source);
      }
    } catch (Exception $ex) {
      $api->execManualLocal('merge --abort');
      $api->execManualLocal('reset --hard HEAD --');

      throw new Exception(
        pht(
          'Local "%s" does not merge cleanly into "%s". Merge or rebase '.
          'local changes so they can merge cleanly.',
          $this->getSourceRef(),
          $this->getTargetFullRef()));
    }

    list($changes) = $api->execxLocal('diff HEAD --');
    $changes = trim($changes);
    if (!strlen($changes)) {
      throw new Exception(
        pht(
          'Merging local "%s" into "%s" produces an empty diff. '.
          'This usually means these changes have already landed.',
          $this->getSourceRef(),
          $this->getTargetFullRef()));
    }

    $api->execxLocal(
      'commit --author %s --date %s -F %s --',
      $original_author,
      $original_date,
      $this->getCommitMessageFile());

    $this->getWorkflow()->didCommitMerge();

    list($stdout) = $api->execxLocal(
      'rev-parse --verify %s',
      'HEAD');
    $this->mergedRef = trim($stdout);
  }

  private function pushChange() {
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

  private function reconcileLocalState() {
    $api = $this->getRepositoryAPI();

    // Try to put the user into the best final state we can. This is very
    // complicated because users are incredibly creative and their local
    // branches may have the same names as branches in the remote but no
    // relationship to them.

    if ($this->localRef != $this->getSourceRef()) {
      // The user ran `arc land X` but was on a different branch, so just put
      // them back wherever they were before.
      $this->writeInfo(
        pht('RESTORE'),
        pht('Switching back to "%s".', $this->localRef));
      $this->restoreLocalState();
      return;
    }

    // We're going to try to find a path to the upstream target branch. We
    // try in two different ways:
    //
    //   - follow the source branch directly along tracking branches until
    //     we reach the upstream; or
    //   - follow a local branch with the same name as the target branch until
    //     we reach the upstream.

    // First, get the path from whatever we landed to wherever it goes.
    $local_branch = $this->getSourceRef();

    $path = $api->getPathToUpstream($local_branch);
    if ($path->getLength()) {
      // We may want to discard the thing we landed from the path, if we're
      // going to delete it. In this case, we don't want to update it or worry
      // if it's dirty.
      if ($this->getSourceRef() == $this->getTargetOnto()) {
        // In this case, we've done something like land "master" onto itself,
        // so we do want to update the actual branch. We're going to use the
        // entire path.
      } else {
        // Otherwise, we're going to delete the branch at the end of the
        // workflow, so throw it away the most-local branch that isn't long
        // for this world.
        $path->removeUpstream($local_branch);

        if (!$path->getLength()) {
          // The local branch tracked upstream directly; however, it
          // may not be the only one to do so.  If there's a local
          // branch of the same name that tracks the remote, try
          // switching to that.
          $local_branch = $this->getTargetOnto();
          list($err) = $api->execManualLocal(
            'rev-parse --verify %s',
            $local_branch);
          if (!$err) {
            $path = $api->getPathToUpstream($local_branch);
          }
          if (!$path->isConnectedToRemote()) {
            $this->writeInfo(
              pht('UPDATE'),
              pht(
                'Local branch "%s" directly tracks remote, staying on '.
                'detached HEAD.',
                $local_branch));
            return;
          }
        }

        $local_branch = head($path->getLocalBranches());
      }
    } else {
      // The source branch has no upstream, so look for a local branch with
      // the same name as the target branch. This corresponds to the common
      // case where you have "master" and checkout local branches from it
      // with "git checkout -b feature", then land onto "master".

      $local_branch = $this->getTargetOnto();

      list($err) = $api->execManualLocal(
        'rev-parse --verify %s',
        $local_branch);
      if ($err) {
        $this->writeInfo(
          pht('UPDATE'),
          pht(
            'Local branch "%s" does not exist, staying on detached HEAD.',
            $local_branch));
        return;
      }

      $path = $api->getPathToUpstream($local_branch);
    }

    if ($path->getCycle()) {
      $this->writeWarn(
        pht('LOCAL CYCLE'),
        pht(
          'Local branch "%s" tracks an upstream but following it leads to '.
          'a local cycle, staying on detached HEAD.',
          $local_branch));
      return;
    }

    if (!$path->isConnectedToRemote()) {
      $this->writeInfo(
        pht('UPDATE'),
        pht(
          'Local branch "%s" is not connected to a remote, staying on '.
          'detached HEAD.',
          $local_branch));
      return;
    }

    $remote_remote = $path->getRemoteRemoteName();
    $remote_branch = $path->getRemoteBranchName();

    $remote_actual = $remote_remote.'/'.$remote_branch;
    $remote_expect = $this->getTargetFullRef();
    if ($remote_actual != $remote_expect) {
      $this->writeInfo(
        pht('UPDATE'),
        pht(
          'Local branch "%s" is connected to a remote ("%s") other than '.
          'the target remote ("%s"), staying on detached HEAD.',
          $local_branch,
          $remote_actual,
          $remote_expect));
      return;
    }

    // If we get this far, we have a sequence of branches which ultimately
    // connect to the remote. We're going to try to update them all in reverse
    // order, from most-upstream to most-local.

    $cascade_branches = $path->getLocalBranches();
    $cascade_branches = array_reverse($cascade_branches);

    // First, check if any of them are ahead of the remote.

    $ahead_of_remote = array();
    foreach ($cascade_branches as $cascade_branch) {
      list($stdout) = $api->execxLocal(
        'log %s..%s --',
        $this->mergedRef,
        $cascade_branch);
      $stdout = trim($stdout);

      if (strlen($stdout)) {
        $ahead_of_remote[$cascade_branch] = $cascade_branch;
      }
    }

    // We're going to handle the last branch (the thing we ultimately intend
    // to check out) differently. It's OK if it's ahead of the remote, as long
    // as we just landed it.

    $local_ahead = isset($ahead_of_remote[$local_branch]);
    unset($ahead_of_remote[$local_branch]);
    $land_self = ($this->getTargetOnto() === $this->getSourceRef());

    // We aren't going to pull anything if anything upstream from us is ahead
    // of the remote, or the local is ahead of the remote and we didn't land
    // it onto itself.
    $skip_pull = ($ahead_of_remote || ($local_ahead && !$land_self));

    if ($skip_pull) {
      $this->writeInfo(
        pht('UPDATE'),
        pht(
          'Local "%s" is ahead of remote "%s". Checking out "%s" but '.
          'not pulling changes.',
          nonempty(head($ahead_of_remote), $local_branch),
          $this->getTargetFullRef(),
          $local_branch));

      $this->writeInfo(
        pht('CHECKOUT'),
        pht(
          'Checking out "%s".',
          $local_branch));

      $api->execxLocal('checkout %s --', $local_branch);

      return;
    }

    // If nothing upstream from our nearest branch is ahead of the remote,
    // pull it all.

    $cascade_targets = array();
    if (!$ahead_of_remote) {
      foreach ($cascade_branches as $cascade_branch) {
        if ($local_ahead && ($local_branch == $cascade_branch)) {
          continue;
        }
        $cascade_targets[] = $cascade_branch;
      }
    }

    if ($cascade_targets) {
      $this->writeInfo(
        pht('UPDATE'),
        pht(
          'Local "%s" tracks target remote "%s", checking out and '.
          'pulling changes.',
          $local_branch,
          $this->getTargetFullRef()));

      foreach ($cascade_targets as $cascade_branch) {
        $this->writeInfo(
          pht('PULL'),
          pht(
            'Checking out and pulling "%s".',
            $cascade_branch));

        $api->execxLocal('checkout %s --', $cascade_branch);
        $api->execxLocal('pull --');
      }

      if (!$local_ahead) {
        return;
      }
    }

    // In this case, the user did something like land a branch onto itself,
    // and the branch is tracking the correct remote. We're going to discard
    // the local state and reset it to the state we just pushed.

    $this->writeInfo(
      pht('RESET'),
      pht(
        'Local "%s" landed into remote "%s", resetting local branch to '.
        'remote state.',
        $this->getTargetOnto(),
        $this->getTargetFullRef()));

    $api->execxLocal('checkout %s --', $local_branch);
    $api->execxLocal('reset --hard %s --', $this->getTargetFullRef());

    return;
  }

  private function destroyLocalBranch() {
    $api = $this->getRepositoryAPI();

    if ($this->getSourceRef() == $this->getTargetOnto()) {
      // If we landed a branch into a branch with the same name, so don't
      // destroy it. This prevents us from cleaning up "master" if you're
      // landing master into itself.
      return;
    }

    // TODO: Maybe this should also recover the proper upstream?

    $recovery_command = csprintf(
      'git checkout -b %R %R',
      $this->getSourceRef(),
      $this->sourceCommit);

    echo tsprintf(
      "%s\n",
      pht('Cleaning up branch "%s"...', $this->getSourceRef()));

    echo tsprintf(
      "%s\n",
      pht('(Use `%s` if you want it back.)', $recovery_command));

    $api->execxLocal('branch -D -- %s', $this->getSourceRef());
  }

  /**
   * Save the local working copy state so we can restore it later.
   */
  private function saveLocalState() {
    $api = $this->getRepositoryAPI();

    $this->localCommit = $api->getWorkingCopyRevision();

    list($ref) = $api->execxLocal('rev-parse --abbrev-ref HEAD');
    $ref = trim($ref);
    if ($ref === 'HEAD') {
      $ref = $this->localCommit;
    }

    $this->localRef = $ref;

    $this->restoreWhenDestroyed = true;
  }

  /**
   * Restore the working copy to the state it was in before we started
   * performing writes.
   */
  private function restoreLocalState() {
    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $this->localRef);
    $api->execxLocal('reset --hard %s --', $this->localCommit);
    $api->execxLocal('submodule update --init --recursive');

    $this->restoreWhenDestroyed = false;
  }

  private function getTargetFullRef() {
    return $this->getTargetRemote().'/'.$this->getTargetOnto();
  }

  private function getAuthorAndDate($commit) {
    $api = $this->getRepositoryAPI();

    // TODO: This is working around Windows escaping problems, see T8298.

    list($info) = $api->execxLocal(
      'log -n1 --format=%C %s --',
      '%aD%n%an%n%ae',
      $commit);

    $info = trim($info);
    list($date, $author, $email) = explode("\n", $info, 3);

    return array(
      "$author <{$email}>",
      $date,
    );
  }

  private function didHoldChanges() {
    $this->writeInfo(
      pht('HOLD'),
      pht(
        'Holding change locally, it has not been pushed.'));

    $push_command = csprintf(
      '$ git push -- %R %R:%R',
      $this->getTargetRemote(),
      $this->mergedRef,
      $this->getTargetOnto());

    $restore_command = csprintf(
      '$ git checkout %R --',
      $this->localRef);

    echo tsprintf(
      "\n%s\n\n".
      "%s\n\n".
      "    %s\n\n".
      "%s\n\n".
      "    %s\n\n".
      "%s\n",
      pht(
        'This local working copy now contains the merged changes in a '.
        'detached state.'),
      pht('You can push the changes manually with this command:'),
      $push_command,
      pht(
        'You can go back to how things were before you ran `arc land` with '.
        'this command:'),
      $restore_command,
      pht(
        'Local branches have not been changed, and are still in exactly the '.
        'same state as before.'));
  }

}
