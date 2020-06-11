<?php

final class ArcanistMercurialLandEngine
  extends ArcanistLandEngine {

  private $ontoBranchMarker;
  private $ontoMarkers;

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
    $commit = $api->getDisplayHash($commit);

    $log->writeStatus(
      pht('SOURCE'),
      pht(
        'Landing the active commit, "%s".',
        $api->getDisplayHash($commit)));

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

      foreach ($unresolved as $key => $symbol) {
        $raw_symbol = $symbol->getSymbol();

        $named_markers = idx($markers, $raw_symbol);
        if (!$named_markers) {
          continue;
        }

        if (count($named_markers) > 1) {
          echo tsprintf(
            "\n%!\n%W\n\n",
            pht('AMBIGUOUS SYMBOL'),
            pht(
              'Symbol "%s" is ambiguous: it matches multiple markers '.
              '(of type "%s"). Use an unambiguous identifier.',
              $raw_symbol,
              $marker_type));

          foreach ($named_markers as $named_marker) {
            echo tsprintf('%s', $named_marker->newRefView());
          }

          echo tsprintf("\n");

          throw new PhutilArgumentUsageException(
            pht(
              'Symbol "%s" is ambiguous.',
              $symbol));
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

    $remote_ref = id(new ArcanistRemoteRef())
      ->setRemoteName($this->getOntoRemote());

    $markers = $api->newMarkerRefQuery()
      ->withRemotes(array($remote_ref))
      ->execute();

    $onto_markers = array();
    $new_markers = array();
    foreach ($onto_refs as $onto_ref) {
      $matches = array();
      foreach ($markers as $marker) {
        if ($marker->getName() === $onto_ref) {
          $matches[] = $marker;
        }
      }

      $match_count = count($matches);
      if ($match_count > 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'TODO: Ambiguous ref.'));
      } else if (!$match_count) {
        $new_bookmark = id(new ArcanistMarkerRef())
          ->setMarkerType(ArcanistMarkerRef::TYPE_BOOKMARK)
          ->setName($onto_ref)
          ->attachRemoteRef($remote_ref);

        $onto_markers[] = $new_bookmark;
        $new_markers[] = $new_bookmark;
      } else {
        $onto_markers[] = head($matches);
      }
    }

    $branches = array();
    foreach ($onto_markers as $onto_marker) {
      if ($onto_marker->isBranch()) {
        $branches[] = $onto_marker;
      }

      $branch_count = count($branches);
      if ($branch_count > 1) {
        echo tsprintf(
          "\n%!\n%W\n\n%W\n\n%W\n\n",
          pht('MULTIPLE "ONTO" BRANCHES'),
          pht(
            'You have selected multiple branches to push changes onto. '.
            'Pushing to multiple branches is not supported by "arc land" '.
            'in Mercurial: Mercurial commits may only belong to one '.
            'branch, so this operation can not be executed atomically.'),
          pht(
            'You may land one branches and any number of bookmarks in a '.
            'single operation.'),
          pht('These branches were selected:'));

        foreach ($branches as $branch) {
          echo tsprintf('%s', $branch->newRefView());
        }

        echo tsprintf("\n");

        throw new PhutilArgumentUsageException(
          pht(
            'Landing onto multiple branches at once is not supported in '.
            'Mercurial.'));
      } else if ($branch_count) {
        $this->ontoBranchMarker = head($branches);
      }
    }

    if ($new_markers) {
      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('CREATE %s BOOKMARK(S)', phutil_count($new_markers)),
        pht(
          'These %s symbol(s) do not exist in the remote. They will be created '.
          'as new bookmarks:',
          phutil_count($new_markers)));


      foreach ($new_markers as $new_marker) {
        echo tsprintf('%s', $new_marker->newRefView());
      }

      echo tsprintf("\n");

      $query = pht(
        'Create %s new remote bookmark(s)?',
        phutil_count($new_markers));

      $this->getWorkflow()
        ->getPrompt('arc.land.create')
        ->setQuery($query)
        ->execute();
    }

    $this->ontoMarkers = $onto_markers;
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

      // TODO: This error handling could probably be cleaner, it will just
      // raise an exception without any context.

      $into_commit = $api->getCanonicalRevisionName($local_ref);

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

  private function fetchTarget(ArcanistLandTarget $target) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $target_name = $target->getRef();

    $remote_ref = id(new ArcanistRemoteRef())
      ->setRemoteName($target->getRemote());

    $markers = $api->newMarkerRefQuery()
      ->withRemotes(array($remote_ref))
      ->withNames(array($target_name))
      ->execute();

    $bookmarks = array();
    $branches = array();
    foreach ($markers as $marker) {
      if ($marker->isBookmark()) {
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

      $target_marker = $bookmark;
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

      $target_marker = $branch;
    }

    if ($target_marker->isBranch()) {
      $err = $this->newPassthru(
        'pull --branch %s -- %s',
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
        'pull --bookmark %s -- %s',
        $target->getRef(),
        $target->getRemote());
    }

    // NOTE: It's possible that between the time we ran "ls-markers" and the
    // time we ran "pull" that the remote changed.

    // It may even have been rewound or rewritten, in which case we did not
    // actually fetch the ref we are about to return as a target. For now,
    // assume this didn't happen: it's so unlikely that it's probably not
    // worth spending 100ms to check.

    // TODO: If the Mercurial command server is revived, this check becomes
    // more reasonable if it's cheap.

    return $target_marker->getCommitHash();
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

    // If we're landing "--onto" a branch, set that as the branch marker
    // before creating the new commit.

    // TODO: We could skip this if we know that the "$into_commit" already
    // has the right branch, which it will if we created it.

    $branch_marker = $this->ontoBranchMarker;
    if ($branch_marker) {
      $api->execxLocal('branch -- %s', $branch_marker->getName());
    }

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

    list($head, $body, $tail) = $this->newPushCommands($into_commit);

    foreach ($head as $command) {
      $api->execxLocal('%Ls', $command);
    }

    try {
      foreach ($body as $command) {
        $this->newPasthru('%Ls', $command);
      }
    } finally {
      foreach ($tail as $command) {
        $api->execxLocal('%Ls', $command);
      }
    }
  }

  private function newPushCommands($into_commit) {
    $api = $this->getRepositoryAPI();

    $head_commands = array();
    $body_commands = array();
    $tail_commands = array();

    $bookmarks = array();
    foreach ($this->ontoMarkers as $onto_marker) {
      if (!$onto_marker->isBookmark()) {
        continue;
      }
      $bookmarks[] = $onto_marker;
    }

    // If we're pushing to bookmarks, move all the bookmarks we want to push
    // to the merge commit. (There doesn't seem to be any way to specify
    // "push commit X as bookmark Y" in Mercurial.)

    $restore = array();
    if ($bookmarks) {
      $markers = $api->newMarkerRefQuery()
        ->withNames(mpull($bookmarks, 'getName'))
        ->withMarkerTypes(array(ArcanistMarkerRef::TYPE_BOOKMARK))
        ->execute();
      $markers = mpull($markers, 'getCommitHash', 'getName');

      foreach ($bookmarks as $bookmark) {
        $bookmark_name = $bookmark->getName();

        $old_position = idx($markers, $bookmark_name);
        $new_position = $into_commit;

        if ($old_position === $new_position) {
          continue;
        }

        $head_commands[] = array(
          'bookmark',
          '--force',
          '--rev',
          hgsprintf('%s', $api->getDisplayHash($new_position)),
          '--',
          $bookmark_name,
        );

        $api->execxLocal(
          'bookmark --force --rev %s -- %s',
          hgsprintf('%s', $new_position),
          $bookmark_name);

        $restore[$bookmark_name] = $old_position;
      }
    }

    // Now, prepare the actual push.

    $argv = array();
    $argv[] = 'push';

    if ($bookmarks) {
      // If we're pushing at least one bookmark, we can just specify the list
      // of bookmarks as things we want to push.
      foreach ($bookmarks as $bookmark) {
        $argv[] = '--bookmark';
        $argv[] = $bookmark->getName();
      }
    } else {
      // Otherwise, specify the commit itself.
      $argv[] = '--rev';
      $argv[] = hgsprintf('%s', $into_commit);
    }

    $argv[] = '--';
    $argv[] = $this->getOntoRemote();

    $body_commands[] = $argv;

    // Finally, restore the bookmarks.

    foreach ($restore as $bookmark_name => $old_position) {
      $tail = array();
      $tail[] = 'bookmark';

      if ($old_position === null) {
        $tail[] = '--delete';
      } else {
        $tail[] = '--force';
        $tail[] = '--rev';
        $tail[] = hgsprintf('%s', $api->getDisplayHash($old_position));
      }

      $tail[] = '--';
      $tail[] = $bookmark_name;

      $tail_commands[] = $tail;
    }

    return array(
      $head_commands,
      $body_commands,
      $tail_commands,
    );
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

    $revs = array();

    // We've rebased all descendants already, so we can safely delete all
    // of these commits.

    $sets = array_reverse($sets);
    foreach ($sets as $set) {
      $commits = $set->getCommits();

      $min_commit = head($commits)->getHash();
      $max_commit = last($commits)->getHash();

      $revs[] = hgsprintf('%s::%s', $min_commit, $max_commit);
    }

    $rev_set = '('.implode(') or (', $revs).')';

    // See PHI45. If we have "hg evolve", get rid of old commits using
    // "hg prune" instead of "hg strip".

    // If we "hg strip" a commit which has an obsolete predecessor, it
    // removes the obsolescence marker and revives the predecessor. This is
    // not desirable: we want to destroy all predecessors of these commits.

    if ($api->getMercurialFeature('evolve')) {
      $api->execxLocal(
        'prune --rev %s',
        $rev_set);
    } else {
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

    list($head, $body, $tail) = $this->newPushCommands($into_commit);
    $commands = array_merge($head, $body, $tail);

    echo tsprintf(
      "\n%!\n%s\n\n",
      pht('HOLD CHANGES'),
      $message);

    echo tsprintf(
      "%s\n\n",
      pht('To push changes manually, run these %s command(s):',
        phutil_count($commands)));

    foreach ($commands as $command) {
      echo tsprintf('%>', csprintf('hg %Ls', $command));
    }

    echo tsprintf("\n");

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
        'Local branches and bookmarks have not been changed, and are still '.
        'in the same state as before.'));
  }

}
