<?php

class ArcanistGitLandEngine
  extends ArcanistLandEngine {

  private $localRef;
  protected $localCommit; // UBER CODE
  protected $sourceCommit; // UBER CODE
  private $mergedRef;
  protected $restoreWhenDestroyed; // UBER CODE
  private $isGitPerforce;

  private function setIsGitPerforce($is_git_perforce) {
    $this->isGitPerforce = $is_git_perforce;
    return $this;
  }

  private function getIsGitPerforce() {
    return $this->isGitPerforce;
  }

  public function parseArguments() {
    $api = $this->getRepositoryAPI();

    $onto = $this->getEngineOnto();
    $this->setTargetOnto($onto);

    $remote = $this->getEngineRemote();

    $is_pushable = $api->isPushableRemote($remote);
    $is_perforce = $api->isPerforceRemote($remote);

    if (!$is_pushable && !$is_perforce) {
      throw new PhutilArgumentUsageException(
        pht(
          'No pushable remote "%s" exists. Use the "--remote" flag to choose '.
          'a valid, pushable remote to land changes onto.',
          $remote));
    }

    if ($is_perforce) {
      $this->setIsGitPerforce(true);
      $this->writeWarn(
        pht('P4 MODE'),
        pht(
          'Operating in Git/Perforce mode after selecting a Perforce '.
          'remote.'));

      if (!$this->getShouldSquash()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Perforce mode does not support the "merge" land strategy. '.
            'Use the "squash" land strategy when landing to a Perforce '.
            'remote (you can use "--squash" to select this strategy).'));
      }
    }

    $this->setTargetRemote($remote);
  }

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
        if ($api->uberHasGitSubmodules()) {
            $api->execxLocal('submodule update --init --recursive');
        }

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
      $this->writeWarn(
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

  protected function verifySourceAndTargetExist() {
    $api = $this->getRepositoryAPI();

    list($err) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getTargetFullRef());

    if ($err) {
      $this->writeWarn(
        pht('TARGET'),
        pht(
          'No local ref exists for branch "%s" in remote "%s", attempting '.
          'fetch...',
          $this->getTargetOnto(),
          $this->getTargetRemote()));

      $api->execManualLocal(
        'fetch %s %s --',
        $this->getTargetRemote(),
        $this->getTargetOnto());

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

      $this->writeInfo(
        pht('FETCHED'),
        pht(
          'Fetched branch "%s" from remote "%s".',
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

  protected function fetchTarget() {
    $api = $this->getRepositoryAPI();

    $ref = $this->getTargetFullRef();

    // NOTE: Although this output isn't hugely useful, we need to passthru
    // instead of using a subprocess here because `git fetch` may prompt the
    // user to enter a password if they're fetching over HTTP with basic
    // authentication. See T10314.

    if ($this->getIsGitPerforce()) {
      $this->writeInfo(
        pht('P4 SYNC'),
        pht('Synchronizing "%s" from Perforce...', $ref));

      $sync_ref = sprintf(
        'refs/remotes/%s/%s',
        $this->getTargetRemote(),
        $this->getTargetOnto());

      $err = $api->execPassthru(
        'p4 sync --silent --branch %R --',
        $sync_ref);

      if ($err) {
        throw new ArcanistUsageException(
          pht(
            'Perforce sync failed! Fix the error and run "arc land" again.'));
      }
    } else {
      $this->writeInfo(
        pht('FETCH'),
        pht('Fetching "%s"...', $ref));

      $err = $api->execPassthru(
        'fetch --quiet -- %s %s',
        $this->getTargetRemote(),
        $this->getTargetOnto());

      if ($err) {
        throw new ArcanistUsageException(
          pht(
            'Fetch failed! Fix the error and run "arc land" again.'));
      }
    }
  }

  protected function updateWorkingCopy() {
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

    // TODO: This could probably be cleaner by asking the API a question
    // about working copy status instead of running a raw diff command. See
    // discussion in T11435.
    list($changes) = $api->execxLocal('diff --no-ext-diff HEAD --');
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

  protected function pushChange() { // UBER CODE
    $api = $this->getRepositoryAPI();

    if ($this->getIsGitPerforce()) {
      $this->writeInfo(
        pht('SUBMITTING'),
        pht('Submitting changes to "%s".', $this->getTargetFullRef()));

      $config_argv = array();

      // Skip the "git p4 submit" interactive editor workflow. We expect
      // the commit message that "arc land" has built to be satisfactory.
      $config_argv[] = '-c';
      $config_argv[] = 'git-p4.skipSubmitEdit=true';

      // Skip the "git p4 submit" confirmation prompt if the user does not edit
      // the submit message.
      $config_argv[] = '-c';
      $config_argv[] = 'git-p4.skipSubmitEditCheck=true';

      $flags_argv = array();

      // Disable implicit "git p4 rebase" as part of submit. We're allowing
      // the implicit "git p4 sync" to go through since this puts us in a
      // state which is generally similar to the state after "git push", with
      // updated remotes.

      // We could do a manual "git p4 sync" with a more narrow "--branch"
      // instead, but it's not clear that this is beneficial.
      $flags_argv[] = '--disable-rebase';

      // Detect moves and submit them to Perforce as move operations.
      $flags_argv[] = '-M';

      // If we run into a conflict, abort the operation. We expect users to
      // fix conflicts and run "arc land" again.
      $flags_argv[] = '--conflict=quit';

      $err = $api->execPassthru(
        '%LR p4 submit %LR --commit %R --',
        $config_argv,
        $flags_argv,
        $this->mergedRef);

      if ($err) {
        throw new ArcanistUsageException(
          pht(
            'Submit failed! Fix the error and run "arc land" again.'));
      }
    } else {
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
            'Push failed! Fix the error and run "arc land" again.'));
      }
    }
  }

  protected function reconcileLocalState() {
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

    $is_perforce = $this->getIsGitPerforce();

    if ($is_perforce) {
      // If we're in Perforce mode, we don't expect to have a meaningful
      // path to the remote: the "p4" remote is not a real remote, and
      // "git p4" commands do not configure branch upstreams to provide
      // a path.

      // Just pretend the target branch is connected directly to the remote,
      // since this is effectively the behavior of Perforce and appears to
      // do the right thing.
      $cascade_branches = array($local_branch);
    } else {
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
    }

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

    if ($is_perforce) {
      // In Perforce, we've already set the remote to the right state with an
      // implicit "git p4 sync" during "git p4 submit", and "git pull" isn't a
      // meaningful operation. We're going to skip this step and jump down to
      // the "git reset --hard" below to get everything into the right state.
    } else if ($cascade_targets) {
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
        $api->execxLocal(
          'pull %s %s --',
          $this->getTargetRemote(),
          $cascade_branch);
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

  protected function destroyLocalBranch() { // UBER CODE
    $api = $this->getRepositoryAPI();
    $source_ref = $this->getSourceRef();

    if ($source_ref == $this->getTargetOnto()) {
      // If we landed a branch into a branch with the same name, so don't
      // destroy it. This prevents us from cleaning up "master" if you're
      // landing master into itself.
      return;
    }

    // TODO: Maybe this should also recover the proper upstream?

    // See T10321. If we were not landing a branch, don't try to clean it up.
    // This happens most often when landing from a detached HEAD.
    $is_branch = $this->isBranch($source_ref);
    if (!$is_branch) {
      echo tsprintf(
        "%s\n",
        pht(
          '(Source "%s" is not a branch, leaving working copy as-is.)',
          $source_ref));
      return;
    }

    $recovery_command = csprintf(
      'git checkout -b %R %R',
      $source_ref,
      $this->sourceCommit);

    echo tsprintf(
      "%s\n",
      pht('Cleaning up branch "%s"...', $source_ref));

    echo tsprintf(
      "%s\n",
      pht('(Use `%s` if you want it back.)', $recovery_command));

    $api->execxLocal('branch -D -- %s', $source_ref);
  }

  /**
   * Save the local working copy state so we can restore it later.
   */
  protected function saveLocalState() {
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
  protected function restoreLocalState() {
    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $this->localRef);
    $api->execxLocal('reset --hard %s --', $this->localCommit);
    if ($api->uberHasGitSubmodules()) {
        $api->execxLocal('submodule update --init --recursive');
    }

    $this->restoreWhenDestroyed = false;
  }

  protected function getTargetFullRef() {
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
    if ($this->getIsGitPerforce()) {
      $this->writeInfo(
        pht('HOLD'),
        pht(
          'Holding change locally, it has not been submitted.'));

      $push_command = csprintf(
        '$ git p4 submit -M --commit %R --',
        $this->mergedRef);
    } else {
      $this->writeInfo(
        pht('HOLD'),
        pht(
          'Holding change locally, it has not been pushed.'));

      $push_command = csprintf(
        '$ git push -- %R %R:%R',
        $this->getTargetRemote(),
        $this->mergedRef,
        $this->getTargetOnto());
    }

    $restore_command = csprintf(
      '$ git checkout %R --',
      $this->localRef);

    echo tsprintf(
      "\n%s\n\n".
      "%s\n\n".
      "    **%s**\n\n".
      "%s\n\n".
      "    **%s**\n\n".
      "%s\n",
      pht(
        'This local working copy now contains the merged changes in a '.
        'detached state.'),
      pht('You can push the changes manually with this command:'),
      $push_command,
      pht(
        'You can go back to how things were before you ran "arc land" with '.
        'this command:'),
      $restore_command,
      pht(
        'Local branches have not been changed, and are still in exactly the '.
        'same state as before.'));
  }

  private function isBranch($ref) {
    $api = $this->getRepositoryAPI();

    list($err) = $api->execManualLocal(
      'show-ref --verify --quiet -- %R',
      'refs/heads/'.$ref);

    return !$err;
  }

  private function getEngineOnto() {
    $source_ref = $this->getSourceRef();

    $onto = $this->getOntoArgument();
    if ($onto !== null) {
      $this->writeInfo(
        pht('TARGET'),
        pht(
          'Landing onto "%s", selected with the "--onto" flag.',
          $onto));
      return $onto;
    }

    $api = $this->getRepositoryAPI();
    $path = $api->getPathToUpstream($source_ref);

    if ($path->getLength()) {
      $cycle = $path->getCycle();
      if ($cycle) {
        $this->writeWarn(
          pht('LOCAL CYCLE'),
          pht(
            'Local branch tracks an upstream, but following it leads to a '.
            'local cycle; ignoring branch upstream.'));

        echo tsprintf(
          "\n    %s\n\n",
          implode(' -> ', $cycle));

      } else {
        if ($path->isConnectedToRemote()) {
          $onto = $path->getRemoteBranchName();
          $this->writeInfo(
            pht('TARGET'),
            pht(
              'Landing onto "%s", selected by following tracking branches '.
              'upstream to the closest remote.',
              $onto));
          return $onto;
        } else {
          $this->writeInfo(
            pht('NO PATH TO UPSTREAM'),
            pht(
              'Local branch tracks an upstream, but there is no path '.
              'to a remote; ignoring branch upstream.'));
        }
      }
    }

    $workflow = $this->getWorkflow();

    $config_key = 'arc.land.onto.default';
    $onto = $workflow->getConfigFromAnySource($config_key);
    if ($onto !== null) {
      $this->writeInfo(
        pht('TARGET'),
        pht(
          'Landing onto "%s", selected by "%s" configuration.',
          $onto,
          $config_key));
      return $onto;
    }

    $onto = 'master';
    $this->writeInfo(
      pht('TARGET'),
      pht(
        'Landing onto "%s", the default target under git.',
        $onto));

    return $onto;
  }

  private function getEngineRemote() {
    $source_ref = $this->getSourceRef();

    $remote = $this->getRemoteArgument();
    if ($remote !== null) {
      $this->writeInfo(
        pht('REMOTE'),
        pht(
          'Using remote "%s", selected with the "--remote" flag.',
          $remote));
      return $remote;
    }

    $api = $this->getRepositoryAPI();
    $path = $api->getPathToUpstream($source_ref);

    $remote = $path->getRemoteRemoteName();
    if ($remote !== null) {
      $this->writeInfo(
        pht('REMOTE'),
        pht(
          'Using remote "%s", selected by following tracking branches '.
          'upstream to the closest remote.',
          $remote));
      return $remote;
    }

    $remote = 'p4';
    if ($api->isPerforceRemote($remote)) {
      $this->writeInfo(
        pht('REMOTE'),
        pht(
          'Using Perforce remote "%s". The existence of this remote implies '.
          'this working copy was synchronized from a Perforce repository.',
          $remote));
      return $remote;
    }

    $remote = 'origin';
    $this->writeInfo(
      pht('REMOTE'),
      pht(
        'Using remote "%s", the default remote under git.',
        $remote));

    return $remote;
  }

}
