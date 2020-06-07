<?php

final class ArcanistMercurialLandEngine
  extends ArcanistLandEngine {

  protected function getDefaultSymbols() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $bookmark = $api->getActiveBookmark();
    if ($bookmark !== null) {

      $log->writeStatus(
        pht('SOURCE'),
        pht(
          'Landing the active bookmark, "%s".',
          $bookmark));

      return array($bookmark);
    }

    $branch = $api->getBranchName();
    if ($branch !== null) {

      $log->writeStatus(
        pht('SOURCE'),
        pht(
          'Landing the current branch, "%s".',
          $branch));

      return array($branch);
    }

    throw new Exception(pht('TODO: Operate on raw revision.'));
  }

  protected function resolveSymbols(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    foreach ($symbols as $symbol) {
      $raw_symbol = $symbol->getSymbol();

      if ($api->isBookmark($raw_symbol)) {
        $hash = $api->getBookmarkCommitHash($raw_symbol);
        $symbol->setCommit($hash);

        // TODO: Set that this is a bookmark?

        continue;
      }

      if ($api->isBranch($raw_symbol)) {
        $hash = $api->getBranchCommitHash($raw_symbol);
        $symbol->setCommit($hash);

        // TODO: Set that this is a branch?

        continue;
      }

      throw new PhutilArgumentUsageException(
        pht(
          'Symbol "%s" is not a bookmark or branch name.',
          $raw_symbol));
    }
  }

  protected function selectOntoRemote(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $remote = $this->newOntoRemote($symbols);

    // TODO: Verify this remote actually exists.

    return $remote;
  }

  private function newOntoRemote(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();
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

    $default_remote = 'default';

    $log->writeStatus(
      pht('ONTO REMOTE'),
      pht(
        'Landing onto remote "%s", the default remote under Mercurial.',
        $default_remote));

    return $default_remote;
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

    $default_onto = 'default';

    $log->writeStatus(
      pht('ONTO TARGET'),
      pht(
        'Landing onto target "%s", the default target under Mercurial.',
        $default_onto));

    return array($default_onto);
  }

  protected function confirmOntoRefs(array $onto_refs) {
    foreach ($onto_refs as $onto_ref) {
      if (!strlen($onto_ref)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Selected "onto" ref "%s" is invalid: the empty string is not '.
            'a valid ref.',
            $onto_ref));
      }
    }
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

      // TODO: Verify that this is a valid path.
      // TODO: Allow a raw URI?

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
    // Make sure that our "into" target is valid.
    $log = $this->getLogEngine();

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
      $api = $this->getRepositoryAPI();
      $local_ref = $this->getIntoRef();

      // TODO: This error handling could probably be cleaner.

      $into_commit = $api->getCanonicalRevisionName($local_ref);

      $log->writeStatus(
        pht('INTO COMMIT'),
        pht(
          'Preparing merge into local target "%s", at commit "%s".',
          $local_ref,
          $this->getDisplayHash($into_commit)));

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
          $this->getDisplayHash($commit)));
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

  private function fetchTarget(ArcanistLandTarget $target) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // TODO: Support bookmarks.
    // TODO: Deal with bookmark save/restore behavior.
    // TODO: Format this nicely with passthru.
    // TODO: Raise a good error message when the ref does not exist.

    $api->execPassthru(
      'pull -b %s -- %s',
      $target->getRef(),
      $target->getRemote());

    // TODO: Deal with multiple branch heads.

    list($stdout) = $api->execxLocal(
      'log --rev %s --template %s --',
      hgsprintf(
        'last(ancestors(%s) and !outgoing(%s))',
        $target->getRef(),
        $target->getRemote()),
      '{node}');

    return trim($stdout);
  }

  protected function selectCommits($into_commit, array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    $commit_map = array();
    foreach ($symbols as $symbol) {
      $symbol_commit = $symbol->getCommit();
      $template = '{node}-{parents}-';

      if ($into_commit === null) {
        list($commits) = $api->execxLocal(
          'log --rev %s --template %s --',
          hgsprintf('reverse(ancestors(%s))', $into_commit),
          $template);
      } else {
        list($commits) = $api->execxLocal(
          'log --rev %s --template %s --',
          hgsprintf(
            'reverse(ancestors(%s) - ancestors(%s))',
            $symbol_commit,
            $into_commit),
          $template);
      }

      $commits = phutil_split_lines($commits, false);
      $is_first = true;
      foreach ($commits as $line) {
        if (!strlen($line)) {
          continue;
        }

        $parts = explode('-', $line, 3);
        if (count($parts) < 3) {
          throw new Exception(
            pht(
              'Unexpected output from "hg log ...": %s',
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

  protected function executeMerge(ArcanistLandCommitSet $set, $into_commit) {
    $api = $this->getRepositoryAPI();

    if ($this->getStrategy() !== 'squash') {
      throw new Exception(pht('TODO: Support merge strategies'));
    }

    // TODO: Add a Mercurial version check requiring 2.1.1 or newer.

    $api->execxLocal(
      'update --rev %s',
      hgsprintf('%s', $into_commit));

    $commits = $set->getCommits();

    $min_commit = last($commits)->getHash();
    $max_commit = head($commits)->getHash();

    $revision_ref = $set->getRevisionRef();
    $commit_message = $revision_ref->getCommitMessage();

    try {
      $argv = array();
      $argv[] = '--dest';
      $argv[] = hgsprintf('%s', $into_commit);

      $argv[] = '--rev';
      $argv[] = hgsprintf('%s..%s', $min_commit, $max_commit);

      $argv[] = '--logfile';
      $argv[] = '-';

      $argv[] = '--keep';
      $argv[] = '--collapse';

      $future = $api->execFutureLocal('rebase %Ls', $argv);
      $future->write($commit_message);
      $future->resolvex();

    } catch (CommandException $ex) {
      // TODO
      // $api->execManualLocal('rebase --abort');
      throw $ex;
    }

    list($stdout) = $api->execxLocal('log --rev tip --template %s', '{node}');
    $new_cursor = trim($stdout);

    return $new_cursor;
  }

  protected function pushChange($into_commit) {
    $api = $this->getRepositoryAPI();

    // TODO: This does not respect "--into" or "--onto" properly.

    $api->execxLocal(
      'push --rev %s -- %s',
      hgsprintf('%s', $into_commit),
      $this->getOntoRemote());
  }

  protected function cascadeState(ArcanistLandCommitSet $set, $into_commit) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // This has no effect when we're executing a merge strategy.
    if (!$this->isSquashStrategy()) {
      return;
    }

    $old_commit = last($set->getCommits())->getHash();
    $new_commit = $into_commit;

    list($output) = $api->execxLocal(
      'log --rev %s --template %s',
      hgsprintf('children(%s)', $old_commit),
      '{node}\n');
    $child_hashes = phutil_split_lines($output, false);

    foreach ($child_hashes as $child_hash) {
      if (!strlen($child_hash)) {
        continue;
      }

      // TODO: If the only heads which are descendants of this child will
      // be deleted, we can skip this rebase?

      try {
        $api->execxLocal(
          'rebase --source %s --dest %s --keep --keepbranches',
          $child_hash,
          $new_commit);
      } catch (CommandException $ex) {
        // TODO: Recover state.
        throw $ex;
      }
    }
  }


  protected function pruneBranches(array $sets) {
    assert_instances_of($sets, 'ArcanistLandCommitSet');
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // This has no effect when we're executing a merge strategy.
    if (!$this->isSquashStrategy()) {
      return;
    }

    $strip = array();

    // We've rebased all descendants already, so we can safely delete all
    // of these commits.

    $sets = array_reverse($sets);
    foreach ($sets as $set) {
      $commits = $set->getCommits();

      $min_commit = head($commits)->getHash();
      $max_commit = last($commits)->getHash();

      $strip[] = hgsprintf('%s::%s', $min_commit, $max_commit);
    }

    $rev_set = '('.implode(') or (', $strip).')';

    // See PHI45. If we have "hg evolve", get rid of old commits using
    // "hg prune" instead of "hg strip".

    // If we "hg strip" a commit which has an obsolete predecessor, it
    // removes the obsolescence marker and revives the predecessor. This is
    // not desirable: we want to destroy all predecessors of these commits.

    try {
      $api->execxLocal(
        '--config extensions.evolve= prune --rev %s',
        $rev_set);
    } catch (CommandException $ex) {
      $api->execxLocal(
        '--config extensions.strip= strip --rev %s',
        $rev_set);
    }
  }

  protected function reconcileLocalState(
    $into_commit,
    ArcanistRepositoryLocalState $state) {

    // TODO: For now, just leave users wherever they ended up.

    $state->discardLocalState();
  }

  protected function didHoldChanges($into_commit) {
    $log = $this->getLogEngine();
    $local_state = $this->getLocalState();

    $message = pht(
      'Holding changes locally, they have not been pushed.');

    // TODO: This is only vaguely correct.

    $push_command = csprintf(
      '$ hg push --rev %s -- %s',
      hgsprintf('%s', $this->getDisplayHash($into_commit)),
      $this->getOntoRemote());

    echo tsprintf(
      "\n%!\n%s\n\n",
      pht('HOLD CHANGES'),
      $message);

    echo tsprintf(
      "%s\n\n    **%s**\n\n",
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
        echo tsprintf("    **%s**\n", $restore_command);
      }

      echo tsprintf("\n");
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Local branches and bookmarks have not been changed, and are still '.
        'in the same state as before.'));
  }
}
