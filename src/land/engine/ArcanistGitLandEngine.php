<?php

final class ArcanistGitLandEngine
  extends ArcanistLandEngine {

  private $isGitPerforce;
  private $landTargetCommitMap = array();
  private $deletedBranches = array();

  private function setIsGitPerforce($is_git_perforce) {
    $this->isGitPerforce = $is_git_perforce;
    return $this;
  }

  private function getIsGitPerforce() {
    return $this->isGitPerforce;
  }

  protected function pruneBranches(array $sets) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $old_commits = array();
    foreach ($sets as $set) {
      $hash = last($set->getCommits())->getHash();
      $old_commits[] = $hash;
    }

    $branch_map = $this->getBranchesForCommits(
      $old_commits,
      $is_contains = false);

    foreach ($branch_map as $branch_name => $branch_hash) {
      $recovery_command = csprintf(
        'git checkout -b %s %s',
        $branch_name,
        $api->getDisplayHash($branch_hash));

      $log->writeStatus(
        pht('CLEANUP'),
        pht('Cleaning up branch "%s". To recover, run:', $branch_name));

      echo tsprintf(
        "\n    **$** %s\n\n",
        $recovery_command);

      $api->execxLocal('branch -D -- %s', $branch_name);
      $this->deletedBranches[$branch_name] = true;
    }
  }

  private function getBranchesForCommits(array $hashes, $is_contains) {
    $api = $this->getRepositoryAPI();

    $format = '%(refname) %(objectname)';

    $result = array();
    foreach ($hashes as $hash) {
      if ($is_contains) {
        $command = csprintf(
          'for-each-ref --contains %s --format %s --',
          $hash,
          $format);
      } else {
        $command = csprintf(
          'for-each-ref --points-at %s --format %s --',
          $hash,
          $format);
      }

      list($foreach_lines) = $api->execxLocal('%C', $command);
      $foreach_lines = phutil_split_lines($foreach_lines, false);

      foreach ($foreach_lines as $line) {
        if (!strlen($line)) {
          continue;
        }

        $expect_parts = 2;
        $parts = explode(' ', $line, $expect_parts);
        if (count($parts) !== $expect_parts) {
          throw new Exception(
            pht(
              'Failed to explode line "%s".',
              $line));
        }

        $ref_name = $parts[0];
        $ref_hash = $parts[1];

        $matches = null;
        $ok = preg_match('(^refs/heads/(.*)\z)', $ref_name, $matches);
        if ($ok === false) {
          throw new Exception(
            pht(
              'Failed to match against branch pattern "%s".',
              $line));
        }

        if (!$ok) {
          continue;
        }

        $result[$matches[1]] = $ref_hash;
      }
    }

    // Sort the result so that branches are processed in natural order.
    $names = array_keys($result);
    natcasesort($names);
    $result = array_select_keys($result, $names);

    return $result;
  }

  protected function cascadeState(ArcanistLandCommitSet $set, $into_commit) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // This has no effect when we're executing a merge strategy.
    if (!$this->isSquashStrategy()) {
      return;
    }

    $min_commit = head($set->getCommits())->getHash();
    $old_commit = last($set->getCommits())->getHash();
    $new_commit = $into_commit;

    $branch_map = $this->getBranchesForCommits(
      array($old_commit),
      $is_contains = true);

    $log = $this->getLogEngine();
    foreach ($branch_map as $branch_name => $branch_head) {
      // If this branch just points at the old state, don't bother rebasing
      // it. We'll update or delete it later.
      if ($branch_head === $old_commit) {
        continue;
      }

      $log->writeStatus(
        pht('CASCADE'),
        pht(
          'Rebasing "%s" onto landed state...',
          $branch_name));

      // If we used "--pick" to select this commit, we want to rebase branches
      // that descend from it onto its ancestor, not onto the landed change.

      // For example, if the change sequence was "W", "X", "Y", "Z" and we
      // landed "Y" onto "master" using "--pick", we want to rebase "Z" onto
      // "X" (so "W" and "X", which it will often depend on, are still
      // its ancestors), not onto the new "master".

      if ($set->getIsPick()) {
        $rebase_target = $min_commit.'^';
      } else {
        $rebase_target = $new_commit;
      }

      try {
        $api->execxLocal(
          'rebase --onto %s -- %s %s',
          $rebase_target,
          $old_commit,
          $branch_name);
      } catch (CommandException $ex) {
        $api->execManualLocal('rebase --abort');
        $api->execManualLocal('reset --hard HEAD --');

        $log->writeWarning(
          pht('REBASE CONFLICT'),
          pht(
            'Branch "%s" does not rebase cleanly from "%s" onto '.
            '"%s", skipping.',
            $branch_name,
            $api->getDisplayHash($old_commit),
            $api->getDisplayHash($rebase_target)));
      }
    }
  }

  private function fetchTarget(ArcanistLandTarget $target) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // NOTE: Although this output isn't hugely useful, we need to passthru
    // instead of using a subprocess here because `git fetch` may prompt the
    // user to enter a password if they're fetching over HTTP with basic
    // authentication. See T10314.

    if ($this->getIsGitPerforce()) {
      $log->writeStatus(
        pht('P4 SYNC'),
        pht(
          'Synchronizing "%s" from Perforce...',
          $target->getRef()));

      $err = $this->newPassthru(
        'p4 sync --silent --branch %s --',
        $target->getRemote().'/'.$target->getRef());
      if ($err) {
        throw new ArcanistUsageException(
          pht(
            'Perforce sync failed! Fix the error and run "arc land" again.'));
      }

      return $this->getLandTargetLocalCommit($target);
    }

    $exists = $this->getLandTargetLocalExists($target);
    if (!$exists) {
      $log->writeWarning(
        pht('TARGET'),
        pht(
          'No local copy of ref "%s" in remote "%s" exists, attempting '.
          'fetch...',
          $target->getRef(),
          $target->getRemote()));

      $this->fetchLandTarget($target, $ignore_failure = true);

      $exists = $this->getLandTargetLocalExists($target);
      if (!$exists) {
        return null;
      }

      $log->writeStatus(
        pht('FETCHED'),
        pht(
          'Fetched ref "%s" from remote "%s".',
          $target->getRef(),
          $target->getRemote()));

      return $this->getLandTargetLocalCommit($target);
    }

    $log->writeStatus(
      pht('FETCH'),
      pht(
        'Fetching "%s" from remote "%s"...',
        $target->getRef(),
        $target->getRemote()));

    $this->fetchLandTarget($target, $ignore_failure = false);

    return $this->getLandTargetLocalCommit($target);
  }

  protected function executeMerge(ArcanistLandCommitSet $set, $into_commit) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $this->confirmLegacyStrategyConfiguration();

    $is_empty = ($into_commit === null);

    if ($is_empty) {
      $empty_commit = ArcanistGitRawCommit::newEmptyCommit();
      $into_commit = $api->writeRawCommit($empty_commit);
    }

    $commits = $set->getCommits();

    $min_commit = head($commits);
    $min_hash = $min_commit->getHash();

    $max_commit = last($commits);
    $max_hash = $max_commit->getHash();

    // NOTE: See T11435 for some history. See PHI1727 for a case where a user
    // modified their working copy while running "arc land". This attempts to
    // resist incorrectly detecting simultaneous working copy modifications
    // as changes.

    list($changes) = $api->execxLocal(
      'diff --no-ext-diff %s --',
      gitsprintf(
        '%s..%s',
        $into_commit,
        $max_hash));
    $changes = trim($changes);
    if (!strlen($changes)) {

      // TODO: We could make a more significant effort to identify the
      // human-readable symbol which led us to try to land this ref.

      throw new PhutilArgumentUsageException(
        pht(
          'Merging local "%s" into "%s" produces an empty diff. '.
          'This usually means these changes have already landed.',
          $api->getDisplayHash($max_hash),
          $api->getDisplayHash($into_commit)));
    }

    $log->writeStatus(
      pht('MERGING'),
      pht(
        '%s %s',
        $api->getDisplayHash($max_hash),
        $max_commit->getDisplaySummary()));

    // See T13576. We have several different approaches for performing the
    // actual merge.
    //
    // In the simplest case, we're using the "merge" strategy. This means
    // we always want to merge the entire history, and we can just use a
    // "git merge" to accomplish our goal. No other approach is permissible
    // here, so if that doesn't work we're all done and just tell the user
    // to go resolve conflicts.
    //
    // If we're using the "squash" strategy, we may be merging a range of
    // commits that aren't direct descendants of any ancestor of the state
    // we're merging into. That is, there may be some ancestors of this
    // range that we do NOT want to merge. A simple way to get into this
    // state is to use "--pick". We need to slice off only the commits we
    // want to merge to ensure we don't bring anything extra along.
    //
    // If history is simple and linear, we can select this slice with
    // "git rebase". However, if history includes merge commits, it seems
    // as though there are many cases where a (non-interactive) rebase is
    // doomed to failure.
    //
    // If a "git rebase" fails, try to "reduce" the slice first, by using
    // a "git merge --squash" to collapse the whole slice on top of its
    // parent. This produces a single non-merge commit with all the changes,
    // which we can then rebase and merge.

    $try = array();
    if ($this->isSquashStrategy() && !$is_empty) {
      $try[] = 'rebase-merge';
      $try[] = 'reduce-rebase-merge';
    } else {
      $try[] = 'merge';
    }

    $merge_exceptions = array();
    $merge_complete = false;
    foreach ($try as $approach) {
      $reduce_min = null;
      $reduce_max = null;

      $rebase_min = null;
      $rebase_max = null;

      $merge_hash = null;
      $force_resolve = false;

      switch ($approach) {
        case 'reduce-rebase-merge':
          $reduce_min = $min_hash;
          $reduce_max = $max_hash;

          $log->writeStatus(
            pht('MERGE'),
            pht('Attempting to reduce and rebase changes.'));
          break;
        case 'rebase-merge':
          $rebase_min = $min_hash;
          $rebase_max = $max_hash;

          $log->writeStatus(
            pht('MERGE'),
            pht('Attempting to rebase changes.'));
          break;
        case 'merge':
          $merge_hash = $max_hash;

          $log->writeStatus(
            pht('MERGE'),
            pht('Attempting to merge changes.'));
          break;
        default:
          throw new Exception(
            pht(
              'Unknown merge approach "%s".',
              $approach));
      }

      try {
        if ($reduce_max) {
          $reduce_dst = $reduce_min.'^';

          // Squash the "into" state on top of the range. The goal is to
          // guarantee that there are no unresolved conflicts between the
          // maximum commit and the "into" state, because we're going to
          // force conflicts to resolve in our favor later.

          $this->applyMergeOperation(
            $into_commit,
            $reduce_max,
            true,
            $is_empty);

          $join_hash = $this->applyCommitOperation(
            sprintf(
              'arc land: join (%s -> %s)',
              $api->getDisplayHash($into_commit),
              $api->getDisplayHash($reduce_max)),
            null,
            null,
            $allow_empty = true);

          // Squash the range, including the new merge, into a single
          // commit. The goal here is to produce a new range with no merge
          // commits so we can rebase it (we'll produce a sequence exactly
          // one commit long).

          $this->applyMergeOperation(
            $join_hash,
            $reduce_dst,
            true,
            $is_empty);

          $reduce_hash = $this->applyCommitOperation(
            sprintf(
              'arc land: reduce (%s..%s -> %s)',
              $api->getDisplayHash($reduce_min),
              $api->getDisplayHash($reduce_max),
              $reduce_dst));

          // We've reduced the range into a new range that is one commit
          // long, has no merge commits, and has no conflicts against the
          // "into" state.

          // We'll rebase it and force conflicts to resolve in favor of the
          // reduced state. The hope is that we've taken sufficient steps to
          // guarantee this resolution is always reasonable.

          $rebase_min = $reduce_hash;
          $rebase_max = $reduce_hash;
          $force_resolve = true;
        }

        if ($rebase_max) {
          $merge_hash = $this->applyRebaseOperation(
            $rebase_min,
            $rebase_max,
            $into_commit,
            $force_resolve);
        }

        $this->applyMergeOperation(
          $merge_hash,
          $into_commit,
          $this->isSquashStrategy(),
          $is_empty);

        $log->writeStatus(pht('DONE'), pht('Merge succeeded.'));

        $merge_complete = true;
      } catch (CommandException $ex) {
        $merge_exceptions[] = $ex;
      }

      if ($merge_complete) {
        break;
      }
    }

    if (!$merge_complete) {
      $direct_symbols = $max_commit->getDirectSymbols();
      $indirect_symbols = $max_commit->getIndirectSymbols();
      if ($direct_symbols) {
        $message = pht(
          'Local commit "%s" (%s) does not merge cleanly into "%s". '.
          'Rebase or merge local changes so they can merge cleanly.',
          $api->getDisplayHash($max_hash),
          $this->getDisplaySymbols($direct_symbols),
          $api->getDisplayHash($into_commit));
      } else if ($indirect_symbols) {
        $message = pht(
          'Local commit "%s" (reachable from: %s) does not merge cleanly '.
          'into "%s". Rebase or merge local changes so they can merge '.
          'cleanly.',
          $api->getDisplayHash($max_hash),
          $this->getDisplaySymbols($indirect_symbols),
          $api->getDisplayHash($into_commit));
      } else {
        $message = pht(
          'Local commit "%s" does not merge cleanly into "%s". Rebase or '.
          'merge local changes so they can merge cleanly.',
          $api->getDisplayHash($max_hash),
          $api->getDisplayHash($into_commit));
      }

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('MERGE CONFLICT'),
        $message);

      if ($this->getHasUnpushedChanges()) {
        echo tsprintf(
          "%?\n\n",
          pht(
            'Use "--incremental" to merge and push changes one by one.'));
      }

      throw new PhutilArgumentUsageException(
        pht('Encountered a merge conflict.'));
    }

    list($original_author, $original_date) = $this->getAuthorAndDate(
      $max_hash);

    $revision_ref = $set->getRevisionRef();
    $commit_message = $revision_ref->getCommitMessage();

    $new_cursor = $this->applyCommitOperation(
      $commit_message,
      $original_author,
      $original_date);

    if ($is_empty) {
      // See T12876. If we're landing into the empty state, we just did a fake
      // merge on top of an empty commit. We're now on a commit with all of the
      // right details except that it has an extra empty commit as a parent.

      // Create a new commit which is the same as the current HEAD, except that
      // it doesn't have the extra parent.

      $raw_commit = $api->readRawCommit($new_cursor);
      if ($this->isSquashStrategy()) {
        $raw_commit->setParents(array());
      } else {
        $raw_commit->setParents(array($merge_hash));
      }
      $new_cursor = $api->writeRawCommit($raw_commit);

      $api->execxLocal('checkout %s --', $new_cursor);
    }

    return $new_cursor;
  }

  protected function pushChange($into_commit) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    if ($this->getIsGitPerforce()) {

      // TODO: Specifying "--onto" more than once is almost certainly an error
      // in Perforce.

      $log->writeStatus(
        pht('SUBMITTING'),
        pht(
          'Submitting changes to "%s".',
          $this->getOntoRemote()));

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

      $err = $this->newPassthru(
        '%LR p4 submit %LR --commit %R --',
        $config_argv,
        $flags_argv,
        $into_commit);
      if ($err) {
        throw new ArcanistLandPushFailureException(
          pht(
            'Submit failed! Fix the error and run "arc land" again.'));
      }

      return;
    }

    $log->writeStatus(
      pht('PUSHING'),
      pht('Pushing changes to "%s".', $this->getOntoRemote()));

    $err = $this->newPassthru(
      'push -- %s %Ls',
      $this->getOntoRemote(),
      $this->newOntoRefArguments($into_commit));

    if ($err) {
      throw new ArcanistLandPushFailureException(
        pht(
          'Push failed! Fix the error and run "arc land" again.'));
    }
  }

  protected function reconcileLocalState(
    $into_commit,
    ArcanistRepositoryLocalState $state) {

    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    // Try to put the user into the best final state we can. This is very
    // complicated because users are incredibly creative and their local
    // branches may, for example, have the same names as branches in the
    // remote but no relationship to them.

    // First, we're going to try to update these local branches:
    //
    //   - the branch we started on originally; and
    //   - the local upstreams of the branch we started on originally; and
    //   - the local branch with the same name as the "into" ref; and
    //   - the local branch with the same name as the "onto" ref.
    //
    // These branches may not all exist and may not all be unique.
    //
    // To be updated, these branches must:
    //
    //   - exist;
    //   - have not been deleted; and
    //   - be connected to the remote we pushed into.

    $update_branches = array();

    $local_ref = $state->getLocalRef();
    if ($local_ref !== null) {
      $update_branches[] = $local_ref;
    }

    $local_path = $state->getLocalPath();
    if ($local_path) {
      foreach ($local_path->getLocalBranches() as $local_branch) {
        $update_branches[] = $local_branch;
      }
    }

    if (!$this->getIntoEmpty() && !$this->getIntoLocal()) {
      $update_branches[] = $this->getIntoRef();
    }

    foreach ($this->getOntoRefs() as $onto_ref) {
      $update_branches[] = $onto_ref;
    }

    $update_branches = array_fuse($update_branches);

    // Remove any branches we know we deleted.
    foreach ($update_branches as $key => $update_branch) {
      if (isset($this->deletedBranches[$update_branch])) {
        unset($update_branches[$key]);
      }
    }

    // Now, remove any branches which don't actually exist.
    foreach ($update_branches as $key => $update_branch) {
      list($err) = $api->execManualLocal(
        'rev-parse --verify %s',
        $update_branch);
      if ($err) {
        unset($update_branches[$key]);
      }
    }

    $is_perforce = $this->getIsGitPerforce();
    if ($is_perforce) {
      // If we're in Perforce mode, we don't expect to have a meaningful
      // path to the remote: the "p4" remote is not a real remote, and
      // "git p4" commands do not configure branch upstreams to provide
      // a path.

      // Additionally, we've already set the remote to the right state with an
      // implicit "git p4 sync" during "git p4 submit", and "git pull" isn't a
      // meaningful operation.

      // We're going to skip everything here and just switch to the most
      // desirable branch (if we can find one), then reset the state (if that
      // operation is safe).

      if (!$update_branches) {
        $log->writeStatus(
          pht('DETACHED HEAD'),
          pht(
            'Unable to find any local branches to update, staying on '.
            'detached head.'));
        $state->discardLocalState();
        return;
      }

      $dst_branch = head($update_branches);
      if (!$this->isAncestorOf($dst_branch, $into_commit)) {
        $log->writeStatus(
          pht('CHECKOUT'),
          pht(
            'Local branch "%s" has unpublished changes, checking it out '.
            'but leaving them in place.',
            $dst_branch));
        $do_reset = false;
      } else {
        $log->writeStatus(
          pht('UPDATE'),
          pht(
            'Switching to local branch "%s".',
            $dst_branch));
        $do_reset = true;
      }

      $api->execxLocal('checkout %s --', $dst_branch);

      if ($do_reset) {
        $api->execxLocal('reset --hard %s --', $into_commit);
      }

      $state->discardLocalState();
      return;
    }

    $onto_refs = array_fuse($this->getOntoRefs());

    $pull_branches = array();
    foreach ($update_branches as $update_branch) {
      $update_path = $api->getPathToUpstream($update_branch);

      // Remove any branches which contain upstream cycles.
      if ($update_path->getCycle()) {
        $log->writeWarning(
          pht('LOCAL CYCLE'),
          pht(
            'Local branch "%s" tracks an upstream but following it leads to '.
            'a local cycle, ignoring branch.',
            $update_branch));
        continue;
      }

      // Remove any branches not connected to a remote.
      if (!$update_path->isConnectedToRemote()) {
        continue;
      }

      // Remove any branches connected to a remote other than the remote
      // we actually pushed to.
      $remote_name = $update_path->getRemoteRemoteName();
      if ($remote_name !== $this->getOntoRemote()) {
        continue;
      }

      // Remove any branches not connected to a branch we pushed to.
      $remote_branch = $update_path->getRemoteBranchName();
      if (!isset($onto_refs[$remote_branch])) {
        continue;
      }

      // This is the most-desirable path between some local branch and
      // an impacted upstream. Select it and continue.
      $pull_branches = $update_path->getLocalBranches();
      break;
    }

    // When we update these branches later, we want to start with the branch
    // closest to the upstream and work our way down.
    $pull_branches = array_reverse($pull_branches);
    $pull_branches = array_fuse($pull_branches);

    // If we started on a branch and it still exists but is not impacted
    // by the changes we made to the remote (i.e., we aren't actually going
    // to pull or update it if we continue), just switch back to it now. It's
    // okay if this branch is completely unrelated to the changes we just
    // landed.

    if ($local_ref !== null) {
      if (isset($update_branches[$local_ref])) {
        if (!isset($pull_branches[$local_ref])) {

          $log->writeStatus(
            pht('RETURN'),
            pht(
              'Returning to original branch "%s" in original state.',
              $local_ref));

          $state->restoreLocalState();
          return;
        }
      }
    }

    // Otherwise, if we don't have any path from the upstream to any local
    // branch, we don't want to switch to some unrelated branch which happens
    // to have the same name as a branch we interacted with. Just stay where
    // we ended up.

    $dst_branch = null;
    if ($pull_branches) {
      $dst_branch = null;
      foreach ($pull_branches as $pull_branch) {
        if (!$this->isAncestorOf($pull_branch, $into_commit)) {

          $log->writeStatus(
            pht('LOCAL CHANGES'),
            pht(
              'Local branch "%s" has unpublished changes, ending updates.',
              $pull_branch));

          break;
        }

        $log->writeStatus(
          pht('UPDATE'),
          pht(
            'Updating local branch "%s"...',
            $pull_branch));

        $api->execxLocal(
          'branch -f %s %s --',
          $pull_branch,
          $into_commit);

        $dst_branch = $pull_branch;
      }
    }

    if ($dst_branch) {
      $log->writeStatus(
        pht('CHECKOUT'),
        pht(
          'Checking out "%s".',
          $dst_branch));

      $api->execxLocal('checkout %s --', $dst_branch);
    } else {
      $log->writeStatus(
        pht('DETACHED HEAD'),
        pht(
          'Unable to find any local branches to update, staying on '.
          'detached head.'));
    }

    $state->discardLocalState();
  }

  private function isAncestorOf($branch, $commit) {
    $api = $this->getRepositoryAPI();

    list($stdout) = $api->execxLocal(
      'merge-base -- %s %s',
      $branch,
      $commit);
    $merge_base = trim($stdout);

    list($stdout) = $api->execxLocal(
      'rev-parse --verify %s',
      $branch);
    $branch_hash = trim($stdout);

    return ($merge_base === $branch_hash);
  }

  private function getAuthorAndDate($commit) {
    $api = $this->getRepositoryAPI();

    list($info) = $api->execxLocal(
      'log -n1 --format=%s %s --',
      '%aD%n%an%n%ae',
      gitsprintf('%s', $commit));

    $info = trim($info);
    list($date, $author, $email) = explode("\n", $info, 3);

    return array(
      "$author <{$email}>",
      $date,
    );
  }

  protected function didHoldChanges($into_commit) {
    $log = $this->getLogEngine();
    $local_state = $this->getLocalState();

    if ($this->getIsGitPerforce()) {
      $message = pht(
        'Holding changes locally, they have not been submitted.');

      $push_command = csprintf(
        'git p4 submit -M --commit %s --',
        $into_commit);
    } else {
      $message = pht(
        'Holding changes locally, they have not been pushed.');

      $push_command = csprintf(
        'git push -- %s %Ls',
        $this->getOntoRemote(),
        $this->newOntoRefArguments($into_commit));
    }

    echo tsprintf(
      "\n%!\n%s\n\n",
      pht('HOLD CHANGES'),
      $message);

    echo tsprintf(
      "%s\n\n%>\n",
      pht('To push changes manually, run this command:'),
      $push_command);

    $restore_commands = $local_state->getRestoreCommandsForDisplay();
    if ($restore_commands) {
      echo tsprintf(
        "%s\n\n",
        pht(
          'To go back to how things were before you ran "arc land", run '.
          'these %s command(s):',
          phutil_count($restore_commands)));

      foreach ($restore_commands as $restore_command) {
        echo tsprintf('%>', $restore_command);
      }

      echo tsprintf("\n");
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Local branches have not been changed, and are still in the '.
        'same state as before.'));
  }

  protected function resolveSymbols(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    foreach ($symbols as $symbol) {
      $raw_symbol = $symbol->getSymbol();

      list($err, $stdout) = $api->execManualLocal(
        'rev-parse --verify %s',
        $raw_symbol);

      if ($err) {
        throw new PhutilArgumentUsageException(
          pht(
            'Branch "%s" does not exist in the local working copy.',
            $raw_symbol));
      }

      $commit = trim($stdout);
      $symbol->setCommit($commit);
    }
  }

  protected function confirmOntoRefs(array $onto_refs) {
    $api = $this->getRepositoryAPI();

    foreach ($onto_refs as $onto_ref) {
      if (!strlen($onto_ref)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Selected "onto" ref "%s" is invalid: the empty string is not '.
            'a valid ref.',
            $onto_ref));
      }
    }

    $markers = $api->newMarkerRefQuery()
      ->withRemotes(array($this->getOntoRemoteRef()))
      ->withNames($onto_refs)
      ->execute();

    $markers = mgroup($markers, 'getName');

    $new_markers = array();
    foreach ($onto_refs as $onto_ref) {
      if (isset($markers[$onto_ref])) {
        // Remote already has a branch with this name, so we're fine: we
        // aren't creatinga new branch.
        continue;
      }

      $new_markers[] = id(new ArcanistMarkerRef())
        ->setMarkerType(ArcanistMarkerRef::TYPE_BRANCH)
        ->setName($onto_ref);
    }

    if ($new_markers) {
      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('CREATE %s BRANCHE(S)', phutil_count($new_markers)),
        pht(
          'These %s symbol(s) do not exist in the remote. They will be '.
          'created as new branches:',
          phutil_count($new_markers)));

      foreach ($new_markers as $new_marker) {
        echo tsprintf('%s', $new_marker->newRefView());
      }

      echo tsprintf("\n");

      $is_hold = $this->getShouldHold();
      if ($is_hold) {
        echo tsprintf(
          "%?\n",
          pht(
            'You are using "--hold", so execution will stop before the '.
            '%s branche(s) are actually created. You will be given '.
            'instructions to create the branches.',
            phutil_count($new_markers)));
      }

      $query = pht(
        'Create %s new branche(s) in the remote?',
        phutil_count($new_markers));

      $this->getWorkflow()
        ->getPrompt('arc.land.create')
        ->setQuery($query)
        ->execute();
    }
  }

  protected function selectOntoRefs(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $log = $this->getLogEngine();

    $onto = $this->getOntoArguments();
    if ($onto) {

      $log->writeStatus(
        pht('ONTO TARGET'),
        pht(
          'Refs were selected with the "--onto" flag: %s.',
          implode(', ', $onto)));

      return $onto;
    }

    $onto = $this->getOntoFromConfiguration();
    if ($onto) {
      $onto_key = $this->getOntoConfigurationKey();

      $log->writeStatus(
        pht('ONTO TARGET'),
        pht(
          'Refs were selected by reading "%s" configuration: %s.',
          $onto_key,
          implode(', ', $onto)));

      return $onto;
    }

    $api = $this->getRepositoryAPI();

    $remote_onto = array();
    foreach ($symbols as $symbol) {
      $raw_symbol = $symbol->getSymbol();
      $path = $api->getPathToUpstream($raw_symbol);

      if (!$path->getLength()) {
        continue;
      }

      $cycle = $path->getCycle();
      if ($cycle) {
        $log->writeWarning(
          pht('LOCAL CYCLE'),
          pht(
            'Local branch "%s" tracks an upstream, but following it leads '.
            'to a local cycle; ignoring branch upstream.',
            $raw_symbol));

        $log->writeWarning(
          pht('LOCAL CYCLE'),
          implode(' -> ', $cycle));

        continue;
      }

      if (!$path->isConnectedToRemote()) {
        $log->writeWarning(
          pht('NO PATH TO REMOTE'),
          pht(
            'Local branch "%s" tracks an upstream, but there is no path '.
            'to a remote; ignoring branch upstream.',
            $raw_symbol));

        continue;
      }

      $onto = $path->getRemoteBranchName();

      $remote_onto[$onto] = $onto;
    }

    if (count($remote_onto) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'The branches you are landing are connected to multiple different '.
          'remote branches via Git branch upstreams. Use "--onto" to select '.
          'the refs you want to push to.'));
    }

    if ($remote_onto) {
      $remote_onto = array_values($remote_onto);

      $log->writeStatus(
        pht('ONTO TARGET'),
        pht(
          'Landing onto target "%s", selected by following tracking branches '.
          'upstream to the closest remote branch.',
          head($remote_onto)));

      return $remote_onto;
    }

    $default_onto = 'master';

    $log->writeStatus(
      pht('ONTO TARGET'),
      pht(
        'Landing onto target "%s", the default target under Git.',
        $default_onto));

    return array($default_onto);
  }

  protected function selectOntoRemote(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $remote = $this->newOntoRemote($symbols);

    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();
    $is_pushable = $api->isPushableRemote($remote);
    $is_perforce = $api->isPerforceRemote($remote);

    if (!$is_pushable && !$is_perforce) {
      throw new PhutilArgumentUsageException(
        pht(
          'No pushable remote "%s" exists. Use the "--onto-remote" flag to '.
          'choose a valid, pushable remote to land changes onto.',
          $remote));
    }

    if ($is_perforce) {
      $this->setIsGitPerforce(true);

      $log->writeWarning(
        pht('P4 MODE'),
        pht(
          'Operating in Git/Perforce mode after selecting a Perforce '.
          'remote.'));

      if (!$this->isSquashStrategy()) {
        throw new PhutilArgumentUsageException(
          pht(
            'Perforce mode does not support the "merge" land strategy. '.
            'Use the "squash" land strategy when landing to a Perforce '.
            'remote (you can use "--squash" to select this strategy).'));
      }
    }

    return $remote;
  }

  private function newOntoRemote(array $onto_symbols) {
    assert_instances_of($onto_symbols, 'ArcanistLandSymbol');
    $log = $this->getLogEngine();

    $remote = $this->getOntoRemoteArgument();
    if ($remote !== null) {

      $log->writeStatus(
        pht('ONTO REMOTE'),
        pht(
          'Remote "%s" was selected with the "--onto-remote" flag.',
          $remote));

      return $remote;
    }

    $remote = $this->getOntoRemoteFromConfiguration();
    if ($remote !== null) {
      $remote_key = $this->getOntoRemoteConfigurationKey();

      $log->writeStatus(
        pht('ONTO REMOTE'),
        pht(
          'Remote "%s" was selected by reading "%s" configuration.',
          $remote,
          $remote_key));

      return $remote;
    }

    $api = $this->getRepositoryAPI();

    $upstream_remotes = array();
    foreach ($onto_symbols as $onto_symbol) {
      $path = $api->getPathToUpstream($onto_symbol->getSymbol());

      $remote = $path->getRemoteRemoteName();
      if ($remote !== null) {
        $upstream_remotes[$remote][] = $onto_symbol;
      }
    }

    if (count($upstream_remotes) > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'The "onto" refs you have selected are connected to multiple '.
          'different remotes via Git branch upstreams. Use "--onto-remote" '.
          'to select a single remote.'));
    }

    if ($upstream_remotes) {
      $upstream_remote = head_key($upstream_remotes);

      $log->writeStatus(
        pht('ONTO REMOTE'),
        pht(
          'Remote "%s" was selected by following tracking branches '.
          'upstream to the closest remote.',
          $remote));

      return $upstream_remote;
    }

    $perforce_remote = 'p4';
    if ($api->isPerforceRemote($remote)) {

      $log->writeStatus(
        pht('ONTO REMOTE'),
        pht(
          'Peforce remote "%s" was selected because the existence of '.
          'this remote implies this working copy was synchronized '.
          'from a Perforce repository.',
          $remote));

      return $remote;
    }

    $default_remote = 'origin';

    $log->writeStatus(
      pht('ONTO REMOTE'),
      pht(
        'Landing onto remote "%s", the default remote under Git.',
        $default_remote));

    return $default_remote;
  }

  protected function selectIntoRemote() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    if ($this->getIntoEmptyArgument()) {
      $this->setIntoEmpty(true);

      $log->writeStatus(
        pht('INTO REMOTE'),
        pht(
          'Will merge into empty state, selected with the "--into-empty" '.
          'flag.'));

      return;
    }

    if ($this->getIntoLocalArgument()) {
      $this->setIntoLocal(true);

      $log->writeStatus(
        pht('INTO REMOTE'),
        pht(
          'Will merge into local state, selected with the "--into-local" '.
          'flag.'));

      return;
    }

    $into = $this->getIntoRemoteArgument();
    if ($into !== null) {

      // TODO: We could allow users to pass a URI argument instead, but
      // this also requires some updates to the fetch logic elsewhere.

      if (!$api->isFetchableRemote($into)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Remote "%s", specified with "--into", is not a valid fetchable '.
            'remote.',
            $into));
      }

      $this->setIntoRemote($into);

      $log->writeStatus(
        pht('INTO REMOTE'),
        pht(
          'Will merge into remote "%s", selected with the "--into" flag.',
          $into));

      return;
    }

    $onto = $this->getOntoRemote();
    $this->setIntoRemote($onto);

    $log->writeStatus(
      pht('INTO REMOTE'),
      pht(
        'Will merge into remote "%s" by default, because this is the remote '.
        'the change is landing onto.',
        $onto));
  }

  protected function selectIntoRef() {
    $log = $this->getLogEngine();

    if ($this->getIntoEmptyArgument()) {
      $log->writeStatus(
        pht('INTO TARGET'),
        pht(
          'Will merge into empty state, selected with the "--into-empty" '.
          'flag.'));

      return;
    }

    $into = $this->getIntoArgument();
    if ($into !== null) {
      $this->setIntoRef($into);

      $log->writeStatus(
        pht('INTO TARGET'),
        pht(
          'Will merge into target "%s", selected with the "--into" flag.',
          $into));

      return;
    }

    $ontos = $this->getOntoRefs();
    $onto = head($ontos);

    $this->setIntoRef($onto);
    if (count($ontos) > 1) {
      $log->writeStatus(
        pht('INTO TARGET'),
        pht(
          'Will merge into target "%s" by default, because this is the first '.
          '"onto" target.',
          $onto));
    } else {
      $log->writeStatus(
        pht('INTO TARGET'),
        pht(
          'Will merge into target "%s" by default, because this is the "onto" '.
          'target.',
          $onto));
    }
  }

  protected function selectIntoCommit() {
    $api = $this->getRepositoryAPI();
    // Make sure that our "into" target is valid.
    $log = $this->getLogEngine();
    $api = $this->getRepositoryAPI();

    if ($this->getIntoEmpty()) {
      // If we're running under "--into-empty", we don't have to do anything.

      $log->writeStatus(
        pht('INTO COMMIT'),
        pht('Preparing merge into the empty state.'));

      return null;
    }

    if ($this->getIntoLocal()) {
      // If we're running under "--into-local", just make sure that the
      // target identifies some actual commit.
      $local_ref = $this->getIntoRef();

      list($err, $stdout) = $api->execManualLocal(
        'rev-parse --verify %s',
        $local_ref);

      if ($err) {
        throw new PhutilArgumentUsageException(
          pht(
            'Local ref "%s" does not exist.',
            $local_ref));
      }

      $into_commit = trim($stdout);

      $log->writeStatus(
        pht('INTO COMMIT'),
        pht(
          'Preparing merge into local target "%s", at commit "%s".',
          $local_ref,
          $api->getDisplayHash($into_commit)));

      return $into_commit;
    }

    $target = id(new ArcanistLandTarget())
      ->setRemote($this->getIntoRemote())
      ->setRef($this->getIntoRef());

    $commit = $this->fetchTarget($target);
    if ($commit !== null) {
      $log->writeStatus(
        pht('INTO COMMIT'),
        pht(
          'Preparing merge into "%s" from remote "%s", at commit "%s".',
          $target->getRef(),
          $target->getRemote(),
          $api->getDisplayHash($commit)));
      return $commit;
    }

    // If we have no valid target and the user passed "--into" explicitly,
    // treat this as an error. For example, "arc land --into Q --onto Q",
    // where "Q" does not exist, is an error.
    if ($this->getIntoArgument()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Ref "%s" does not exist in remote "%s".',
          $target->getRef(),
          $target->getRemote()));
    }

    // Otherwise, treat this as implying "--into-empty". For example,
    // "arc land --onto Q", where "Q" does not exist, is equivalent to
    // "arc land --into-empty --onto Q".
    $this->setIntoEmpty(true);

    $log->writeStatus(
      pht('INTO COMMIT'),
      pht(
        'Preparing merge into the empty state to create target "%s" '.
        'in remote "%s".',
        $target->getRef(),
        $target->getRemote()));

    return null;
  }

  private function getLandTargetLocalCommit(ArcanistLandTarget $target) {
    $commit = $this->resolveLandTargetLocalCommit($target);

    if ($commit === null) {
      throw new Exception(
        pht(
          'No ref "%s" exists in remote "%s".',
          $target->getRef(),
          $target->getRemote()));
    }

    return $commit;
  }

  private function getLandTargetLocalExists(ArcanistLandTarget $target) {
    $commit = $this->resolveLandTargetLocalCommit($target);
    return ($commit !== null);
  }

  private function resolveLandTargetLocalCommit(ArcanistLandTarget $target) {
    $target_key = $target->getLandTargetKey();

    if (!array_key_exists($target_key, $this->landTargetCommitMap)) {
      $full_ref = sprintf(
        'refs/remotes/%s/%s',
        $target->getRemote(),
        $target->getRef());

      $api = $this->getRepositoryAPI();

      list($err, $stdout) = $api->execManualLocal(
        'rev-parse --verify %s',
        $full_ref);

      if ($err) {
        $result = null;
      } else {
        $result = trim($stdout);
      }

      $this->landTargetCommitMap[$target_key] = $result;
    }

    return $this->landTargetCommitMap[$target_key];
  }

  private function fetchLandTarget(
    ArcanistLandTarget $target,
    $ignore_failure = false) {
    $api = $this->getRepositoryAPI();

    $err = $this->newPassthru(
      'fetch --no-tags --quiet -- %s %s',
      $target->getRemote(),
      $target->getRef());
    if ($err && !$ignore_failure) {
      throw new ArcanistUsageException(
        pht(
          'Fetch of "%s" from remote "%s" failed! Fix the error and '.
          'run "arc land" again.',
          $target->getRef(),
          $target->getRemote()));
    }

    // TODO: If the remote is a bare URI, we could read ".git/FETCH_HEAD"
    // here and write the commit into the map. For now, settle for clearing
    // the cache.

    // We could also fetch into some named "refs/arc-land-temporary" named
    // ref, then read that.

    if (!$err) {
      $target_key = $target->getLandTargetKey();
      unset($this->landTargetCommitMap[$target_key]);
    }
  }

  protected function selectCommits($into_commit, array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    $commit_map = array();
    foreach ($symbols as $symbol) {
      $symbol_commit = $symbol->getCommit();
      $format = '--format=%H%x00%P%x00%s%x00';

      if ($into_commit === null) {
        list($commits) = $api->execxLocal(
          'log %s %s --',
          $format,
          gitsprintf('%s', $symbol_commit));
      } else {
        list($commits) = $api->execxLocal(
          'log %s %s --not %s --',
          $format,
          gitsprintf('%s', $symbol_commit),
          gitsprintf('%s', $into_commit));
      }

      $commits = phutil_split_lines($commits, false);
      $is_first = true;
      foreach ($commits as $line) {
        if (!strlen($line)) {
          continue;
        }

        $parts = explode("\0", $line, 4);
        if (count($parts) < 3) {
          throw new Exception(
            pht(
              'Unexpected output from "git log ...": %s',
              $line));
        }

        $hash = $parts[0];
        if (!isset($commit_map[$hash])) {
          $parents = $parts[1];
          $parents = trim($parents);
          if (strlen($parents)) {
            $parents = explode(' ', $parents);
          } else {
            $parents = array();
          }

          $summary = $parts[2];

          $commit_map[$hash] = id(new ArcanistLandCommit())
            ->setHash($hash)
            ->setParents($parents)
            ->setSummary($summary);
        }

        $commit = $commit_map[$hash];
        if ($is_first) {
          $commit->addDirectSymbol($symbol);
          $is_first = false;
        }

        $commit->addIndirectSymbol($symbol);
      }
    }

    return $this->confirmCommits($into_commit, $symbols, $commit_map);
  }

  protected function getDefaultSymbols() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $branch = $api->getBranchName();
    if ($branch !== null) {
      $log->writeStatus(
        pht('SOURCE'),
        pht(
          'Landing the current branch, "%s".',
          $branch));

      return array($branch);
    }

    $commit = $api->getCurrentCommitRef();

    $log->writeStatus(
      pht('SOURCE'),
      pht(
        'Landing the current HEAD, "%s".',
        $commit->getCommitHash()));

    return array($commit->getCommitHash());
  }

  private function newOntoRefArguments($into_commit) {
    $api = $this->getRepositoryAPI();
    $refspecs = array();

    foreach ($this->getOntoRefs() as $onto_ref) {
      $refspecs[] = sprintf(
        '%s:refs/heads/%s',
        $api->getDisplayHash($into_commit),
        $onto_ref);
    }

    return $refspecs;
  }

  private function confirmLegacyStrategyConfiguration() {
    // TODO: See T13547. Remove this check in the future. This prevents users
    // from accidentally executing a "squash" workflow under a configuration
    // which would previously have executed a "merge" workflow.

    // We're fine if we have an explicit "--strategy".
    if ($this->getStrategyArgument() !== null) {
      return;
    }

    // We're fine if we have an explicit "arc.land.strategy".
    if ($this->getStrategyFromConfiguration() !== null) {
      return;
    }

    // We're fine if "history.immutable" is not set to "true".
    $source_list = $this->getWorkflow()->getConfigurationSourceList();
    $config_list = $source_list->getStorageValueList('history.immutable');
    if (!$config_list) {
      return;
    }

    $config_value = (bool)last($config_list)->getValue();
    if (!$config_value) {
      return;
    }

    // We're in trouble: we would previously have selected "merge" and will
    // now select "squash". Make sure the user knows what they're in for.

    echo tsprintf(
      "\n%!\n%W\n\n",
      pht('MERGE STRATEGY IS AMBIGUOUS'),
      pht(
        'See <%s>. The default merge strategy under Git with '.
        '"history.immutable" has changed from "merge" to "squash". Your '.
        'configuration is ambiguous under this behavioral change. '.
        '(Use "--strategy" or configure "arc.land.strategy" to bypass '.
        'this check.)',
        'https://secure.phabricator.com/T13547'));

    throw new PhutilArgumentUsageException(
      pht(
        'Desired merge strategy is ambiguous, choose an explicit strategy.'));
  }

  private function applyRebaseOperation(
    $src_min,
    $src_max,
    $dst,
    $force_resolve) {

    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $src_max);

    $argv = array();
    $argv[] = '--onto';
    $argv[] = gitsprintf('%s', $dst);

    if ($force_resolve) {
      $argv[] = '--strategy';
      $argv[] = 'recursive';
      $argv[] = '--strategy-option';
      $argv[] = 'theirs';
    }

    $argv[] = '--';
    $argv[] = gitsprintf('%s', $src_min.'^');

    try {
      $api->execxLocal('rebase %Ls', $argv);
    } catch (CommandException $ex) {
      $api->execManualLocal('rebase --abort');
      $api->execManualLocal('reset --hard HEAD --');
      throw $ex;
    }

    $merge_hash = $api->getCanonicalRevisionName('HEAD');

    return $merge_hash;
  }

  private function applyMergeOperation($src, $dst, $is_squash, $is_empty) {
    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $dst);

    $argv = array();
    $argv[] = '--no-stat';
    $argv[] = '--no-commit';

    // When we're merging into the empty state, Git refuses to perform the
    // merge until we tell it explicitly that we're doing something unusual.
    if ($is_empty) {
      $argv[] = '--allow-unrelated-histories';
    }

    if ($is_squash) {
      // NOTE: We're explicitly specifying "--ff" to override the presence
      // of "merge.ff" options in user configuration.
      $argv[] = '--ff';
      $argv[] = '--squash';
    } else {
      $argv[] = '--no-ff';
    }

    $argv[] = '--';

    $argv[] = $src;

    try {
      $api->execxLocal('merge %Ls', $argv);
    } catch (CommandException $ex) {
      $api->execManualLocal('merge --abort');
      $api->execManualLocal('reset --hard HEAD');
      throw $ex;
    }
  }

  private function applyCommitOperation(
    $message,
    $author = null,
    $date = null,
    $allow_empty = false) {

    $api = $this->getRepositoryAPI();

    $argv = array();
    if ($author !== null) {
      $argv[] = '--author';
      $argv[] = $author;
    }

    if ($date !== null) {
      $argv[] = '--date';
      $argv[] = $date;
    }

    if ($allow_empty) {
      $argv[] = '--allow-empty';
    }

    $future = $api->execFutureLocal(
      'commit %Ls -F - --',
      $argv);
    $future->write($message);
    $future->resolvex();

    list($stdout) = $api->execxLocal('rev-parse --verify %s', 'HEAD');
    $new_commit = trim($stdout);

    return $new_commit;
  }


}
