<?php

final class ArcanistMercurialLandEngine
  extends ArcanistLandEngine {

  protected function getDefaultSymbols() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    // TODO: In Mercurial, you normally can not create a branch and a bookmark
    // with the same name. However, you can fetch a branch or bookmark from
    // a remote that has the same name as a local branch or bookmark of the
    // other type, and end up with a local branch and bookmark with the same
    // name. We should detect this and treat it as an error.

    // TODO: In Mercurial, you can create local bookmarks named
    // "default@default" and similar which do not surive a round trip through
    // a remote. Possibly, we should disallow interacting with these bookmarks.

    $markers = $api->newMarkerRefQuery()
      ->withIsActive(true)
      ->execute();

    $bookmark = null;
    foreach ($markers as $marker) {
      if ($marker->isBookmark()) {
        $bookmark = $marker->getName();
        break;
      }
    }

    if ($bookmark !== null) {
      $log->writeStatus(
        pht('SOURCE'),
        pht(
          'Landing the active bookmark, "%s".',
          $bookmark));

      return array($bookmark);
    }

    $branch = null;
    foreach ($markers as $marker) {
      if ($marker->isBranch()) {
        $branch = $marker->getName();
        break;
      }
    }

    if ($branch !== null) {
      $log->writeStatus(
        pht('SOURCE'),
        pht(
          'Landing the active branch, "%s".',
          $branch));

      return array($branch);
    }

    $commit = $api->getCanonicalRevisionName('.');
    $commit = $this->getDisplayHash($commit);

    $log->writeStatus(
      pht('SOURCE'),
      pht(
        'Landing the active commit, "%s".',
        $this->getDisplayHash($commit)));

    return array($commit);
  }

  protected function resolveSymbols(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    $marker_types = array(
      ArcanistMarkerRef::TYPE_BOOKMARK,
      ArcanistMarkerRef::TYPE_BRANCH,
    );

    $unresolved = $symbols;
    foreach ($marker_types as $marker_type) {
      $markers = $api->newMarkerRefQuery()
        ->withMarkerTypes(array($marker_type))
        ->execute();

      $markers = mgroup($markers, 'getName');

      foreach ($unresolved as $key =>  $symbol) {
        $raw_symbol = $symbol->getSymbol();

        $named_markers = idx($markers, $raw_symbol);
        if (!$named_markers) {
          continue;
        }

        if (count($named_markers) > 1) {
          throw new PhutilArgumentUsageException(
            pht(
              'Symbol "%s" is ambiguous: it matches multiple markers '.
              '(of type "%s"). Use an unambiguous identifier.',
              $raw_symbol,
              $marker_type));
        }

        $marker = head($named_markers);

        $symbol->setCommit($marker->getCommitHash());

        unset($unresolved[$key]);
      }
    }

    foreach ($unresolved as $symbol) {
      $raw_symbol = $symbol->getSymbol();

      // TODO: This doesn't have accurate error behavior if the user provides
      // a revset like "x::y".
      try {
        $commit = $api->getCanonicalRevisionName($raw_symbol);
      } catch (CommandException $ex) {
        $commit = null;
      }

      if ($commit === null) {
        throw new PhutilArgumentUsageException(
          pht(
            'Symbol "%s" does not identify a bookmark, branch, or commit.',
            $raw_symbol));
      }

      $symbol->setCommit($commit);
    }
  }

  protected function selectOntoRemote(array $symbols) {
    assert_instances_of($symbols, 'ArcanistLandSymbol');
    $api = $this->getRepositoryAPI();

    $remote = $this->newOntoRemote($symbols);

    $remote_ref = $api->newRemoteRefQuery()
      ->withNames(array($remote))
      ->executeOne();
    if (!$remote_ref) {
      throw new PhutilArgumentUsageException(
        pht(
          'No remote "%s" exists in this repository.',
          $remote));
    }

    // TODO: Allow selection of a bare URI.

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

      $remote_ref = $api->newRemoteRefQuery()
        ->withNames(array($into))
        ->executeOne();
      if (!$remote_ref) {
        throw new PhutilArgumentUsageException(
          pht(
            'No remote "%s" exists in this repository.',
            $into));
      }

      // TODO: Allow a raw URI.

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

    // See T9948. If the user specified "--into X", we don't know if it's a
    // branch, a bookmark, or a symbol which doesn't exist yet.

    // In native Mercurial it is difficult to figure this out, so we use
    // an extension to provide a command which works like "git ls-remote".

    // NOTE: We're using passthru on this because it's a remote command and
    // may prompt the user for credentials.

    $tmpfile = new TempFile();
    Filesystem::remove($tmpfile);

    $command = $this->newPassthruCommand(
      '%Ls arc-ls-remote --output %s -- %s',
      $api->getMercurialExtensionArguments(),
      phutil_string_cast($tmpfile),
      $target->getRemote());

    $command->setDisplayCommand(
      'hg ls-remote -- %s',
      $target->getRemote());

    $err = $command->execute();
    if ($err) {
      throw new Exception(
        pht(
          'Call to "hg arc-ls-remote" failed with error "%s".',
          $err));
    }

    $raw_data = Filesystem::readFile($tmpfile);
    unset($tmpfile);

    $markers = phutil_json_decode($raw_data);

    $target_name = $target->getRef();

    $bookmarks = array();
    $branches = array();
    foreach ($markers as $marker) {
      if ($marker['name'] !== $target_name) {
        continue;
      }

      if ($marker['type'] === 'bookmark') {
        $bookmarks[] = $marker;
      } else {
        $branches[] = $marker;
      }
    }

    if (!$bookmarks && !$branches) {
      throw new PhutilArgumentUsageException(
        pht(
          'Remote "%s" has no bookmark or branch named "%s".',
          $target->getRemote(),
          $target->getRef()));
    }

    if ($bookmarks && $branches) {
      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('AMBIGUOUS MARKER'),
        pht(
          'In remote "%s", the name "%s" identifies one or more branch '.
          'heads and one or more bookmarks. Close, rename, or delete all '.
          'but one of these markers, or pull the state you want to merge '.
          'into and use "--into-local --into <hash>" to disambiguate the '.
          'desired merge target.',
          $target->getRemote(),
          $target->getRef()));

      throw new PhutilArgumentUsageException(
        pht('Merge target is ambiguous.'));
    }

    $is_bookmark = false;
    $is_branch = false;

    if ($bookmarks) {
      if (count($bookmarks) > 1) {
        throw new Exception(
          pht(
            'Remote "%s" has multiple bookmarks with name "%s". This '.
            'is unexpected.',
            $target->getRemote(),
            $target->getRef()));
      }
      $bookmark = head($bookmarks);

      $target_hash = $bookmark['node'];
      $is_bookmark = true;
    }

    if ($branches) {
      if (count($branches) > 1) {
        echo tsprintf(
          "\n%!\n%W\n\n",
          pht('MULTIPLE BRANCH HEADS'),
          pht(
            'Remote "%s" has multiple branch heads named "%s". Close all '.
            'but one, or pull the head you want and use "--into-local '.
            '--into <hash>" to specify an explicit merge target.',
            $target->getRemote(),
            $target->getRef()));

        throw new PhutilArgumentUsageException(
          pht(
            'Remote branch has multiple heads.'));
      }

      $branch = head($branches);

      $target_hash = $branch['node'];
      $is_branch = true;
    }

    if ($is_branch) {
      $err = $this->newPassthru(
        'pull -b %s -- %s',
        $target->getRef(),
        $target->getRemote());
    } else {

      // NOTE: This may have side effects:
      //
      //   - It can create a "bookmark@remote" bookmark if there is a local
      //     bookmark with the same name that is not an ancestor.
      //   - It can create an arbitrary number of other bookmarks.
      //
      // Since these seem to generally be intentional behaviors in Mercurial,
      // and should theoretically be familiar to Mercurial users, just accept
      // them as the cost of doing business.

      $err = $this->newPassthru(
        'pull -B %s -- %s',
        $target->getRef(),
        $target->getRemote());
    }

    // NOTE: It's possible that between the time we ran "ls-remote" and the
    // time we ran "pull" that the remote changed.

    // It may even have been rewound or rewritten, in which case we did not
    // actually fetch the ref we are about to return as a target. For now,
    // assume this didn't happen: it's so unlikely that it's probably not
    // worth spending 100ms to check.

    // TODO: If the Mercurial command server is revived, this check becomes
    // more reasonable if it's cheap.

    return $target_hash;
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

    $this->newPassthru(
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
