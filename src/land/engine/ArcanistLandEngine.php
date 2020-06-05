<?php

abstract class ArcanistLandEngine extends Phobject {

  private $workflow;
  private $viewer;
  private $logEngine;
  private $repositoryAPI;

  private $sourceRefs;
  private $shouldHold;
  private $shouldKeep;
  private $shouldPreview;
  private $isIncremental;
  private $ontoRemoteArgument;
  private $ontoArguments;
  private $intoEmptyArgument;
  private $intoLocalArgument;
  private $intoRemoteArgument;
  private $intoArgument;
  private $strategyArgument;
  private $strategy;

  private $revisionSymbol;
  private $revisionSymbolRef;

  private $ontoRemote;
  private $ontoRefs;
  private $intoRemote;
  private $intoRef;
  private $intoEmpty;
  private $intoLocal;

  final public function setViewer($viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setOntoRemote($onto_remote) {
    $this->ontoRemote = $onto_remote;
    return $this;
  }

  final public function getOntoRemote() {
    return $this->ontoRemote;
  }

  final public function setOntoRefs($onto_refs) {
    $this->ontoRefs = $onto_refs;
    return $this;
  }

  final public function getOntoRefs() {
    return $this->ontoRefs;
  }

  final public function setIntoRemote($into_remote) {
    $this->intoRemote = $into_remote;
    return $this;
  }

  final public function getIntoRemote() {
    return $this->intoRemote;
  }

  final public function setIntoRef($into_ref) {
    $this->intoRef = $into_ref;
    return $this;
  }

  final public function getIntoRef() {
    return $this->intoRef;
  }

  final public function setIntoEmpty($into_empty) {
    $this->intoEmpty = $into_empty;
    return $this;
  }

  final public function getIntoEmpty() {
    return $this->intoEmpty;
  }

  final public function setIntoLocal($into_local) {
    $this->intoLocal = $into_local;
    return $this;
  }

  final public function getIntoLocal() {
    return $this->intoLocal;
  }

  final public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  final public function getWorkflow() {
    return $this->workflow;
  }

  final public function setRepositoryAPI(
    ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  final public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  final public function setLogEngine(ArcanistLogEngine $log_engine) {
    $this->logEngine = $log_engine;
    return $this;
  }

  final public function getLogEngine() {
    return $this->logEngine;
  }

  final public function setShouldHold($should_hold) {
    $this->shouldHold = $should_hold;
    return $this;
  }

  final public function getShouldHold() {
    return $this->shouldHold;
  }

  final public function setShouldKeep($should_keep) {
    $this->shouldKeep = $should_keep;
    return $this;
  }

  final public function getShouldKeep() {
    return $this->shouldKeep;
  }

  final public function setStrategy($strategy) {
    $this->strategy = $strategy;
    return $this;
  }

  final public function getStrategy() {
    return $this->strategy;
  }

  final public function setRevisionSymbol($revision_symbol) {
    $this->revisionSymbol = $revision_symbol;
    return $this;
  }

  final public function getRevisionSymbol() {
    return $this->revisionSymbol;
  }

  final public function setRevisionSymbolRef(
    ArcanistRevisionSymbolRef $revision_ref) {
    $this->revisionSymbolRef = $revision_ref;
    return $this;
  }

  final public function getRevisionSymbolRef() {
    return $this->revisionSymbolRef;
  }

  final public function setShouldPreview($should_preview) {
    $this->shouldPreview = $should_preview;
    return $this;
  }

  final public function getShouldPreview() {
    return $this->shouldPreview;
  }

  final public function setSourceRefs(array $source_refs) {
    $this->sourceRefs = $source_refs;
    return $this;
  }

  final public function getSourceRefs() {
    return $this->sourceRefs;
  }

  final public function setOntoRemoteArgument($remote_argument) {
    $this->ontoRemoteArgument = $remote_argument;
    return $this;
  }

  final public function getOntoRemoteArgument() {
    return $this->ontoRemoteArgument;
  }

  final public function setOntoArguments(array $onto_arguments) {
    $this->ontoArguments = $onto_arguments;
    return $this;
  }

  final public function getOntoArguments() {
    return $this->ontoArguments;
  }

  final public function setIsIncremental($is_incremental) {
    $this->isIncremental = $is_incremental;
    return $this;
  }

  final public function getIsIncremental() {
    return $this->isIncremental;
  }

  final public function setIntoEmptyArgument($into_empty_argument) {
    $this->intoEmptyArgument = $into_empty_argument;
    return $this;
  }

  final public function getIntoEmptyArgument() {
    return $this->intoEmptyArgument;
  }

  final public function setIntoLocalArgument($into_local_argument) {
    $this->intoLocalArgument = $into_local_argument;
    return $this;
  }

  final public function getIntoLocalArgument() {
    return $this->intoLocalArgument;
  }

  final public function setIntoRemoteArgument($into_remote_argument) {
    $this->intoRemoteArgument = $into_remote_argument;
    return $this;
  }

  final public function getIntoRemoteArgument() {
    return $this->intoRemoteArgument;
  }

  final public function setIntoArgument($into_argument) {
    $this->intoArgument = $into_argument;
    return $this;
  }

  final public function getIntoArgument() {
    return $this->intoArgument;
  }

  final protected function getOntoFromConfiguration() {
    $config_key = $this->getOntoConfigurationKey();
    return $this->getWorkflow()->getConfig($config_key);
  }

  final protected function getOntoConfigurationKey() {
    return 'arc.land.onto';
  }

  final protected function getOntoRemoteFromConfiguration() {
    $config_key = $this->getOntoRemoteConfigurationKey();
    return $this->getWorkflow()->getConfig($config_key);
  }

  final protected function getOntoRemoteConfigurationKey() {
    return 'arc.land.onto-remote';
  }

  final protected function confirmRevisions(array $sets) {
    assert_instances_of($sets, 'ArcanistLandCommitSet');

    $revision_refs = mpull($sets, 'getRevisionRef');
    $viewer = $this->getViewer();
    $viewer_phid = $viewer->getPHID();

    $unauthored = array();
    foreach ($revision_refs as $revision_ref) {
      $author_phid = $revision_ref->getAuthorPHID();
      if ($author_phid !== $viewer_phid) {
        $unauthored[] = $revision_ref;
      }
    }

    if ($unauthored) {
      $this->getWorkflow()->loadHardpoints(
        $unauthored,
        array(
          ArcanistRevisionRef::HARDPOINT_AUTHORREF,
        ));

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('NOT REVISION AUTHOR'),
        pht(
          'You are landing revisions which you ("%s") are not the author of:',
          $viewer->getMonogram()));

      foreach ($unauthored as $revision_ref) {
        $display_ref = $revision_ref->newDisplayRef();

        $author_ref = $revision_ref->getAuthorRef();
        if ($author_ref) {
          $display_ref->appendLine(
            pht(
              'Author: %s',
              $author_ref->getMonogram()));
        }

        echo tsprintf('%s', $display_ref);
      }

      echo tsprintf(
        "\n%?\n",
        pht(
          'Use "Commandeer" in the web interface to become the author of '.
          'a revision.'));

      $query = pht('Land revisions you are not the author of?');

      $this->getWorkflow()
        ->getPrompt('arc.land.unauthored')
        ->setQuery($query)
        ->execute();
    }

    $planned = array();
    $closed = array();
    $not_accepted = array();
    foreach ($revision_refs as $revision_ref) {
      if ($revision_ref->isStatusChangesPlanned()) {
        $planned[] = $revision_ref;
      } else if ($revision_ref->isStatusClosed()) {
        $closed[] = $revision_ref;
      } else if (!$revision_ref->isStatusAccepted()) {
        $not_accepted[] = $revision_ref;
      }
    }

    // See T10233. Previously, this prompt was bundled with the generic "not
    // accepted" prompt, but users found it confusing and interpreted the
    // prompt as a bug.

    if ($planned) {
      $example_ref = head($planned);

      echo tsprintf(
        "\n%!\n%W\n\n%W\n\n%W\n\n",
        pht('%s REVISION(S) HAVE CHANGES PLANNED', phutil_count($planned)),
        pht(
          'You are landing %s revision(s) which are currently in the state '.
          '"%s", indicating that you expect to revise them before moving '.
          'forward.',
          phutil_count($planned),
          $example_ref->getStatusDisplayName()),
        pht(
          'Normally, you should update these %s revision(s), submit them '.
          'for review, and wait for reviewers to accept them before '.
          'you continue. To resubmit a revision for review, either: '.
          'update the revision with revised changes; or use '.
          '"Request Review" from the web interface.',
          phutil_count($planned)),
        pht(
          'These %s revision(s) have changes planned:',
          phutil_count($planned)));

      foreach ($planned as $revision_ref) {
        echo tsprintf('%s', $revision_ref->newDisplayRef());
      }

      $query = pht(
        'Land %s revision(s) with changes planned?',
        phutil_count($planned));

      $this->getWorkflow()
        ->getPrompt('arc.land.changes-planned')
        ->setQuery($query)
        ->execute();
    }

    // See PHI1727. Previously, this prompt was bundled with the generic
    // "not accepted" prompt, but at least one user found it confusing.

    if ($closed) {
      $example_ref = head($closed);

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('%s REVISION(S) ARE ALREADY CLOSED', phutil_count($closed)),
        pht(
          'You are landing %s revision(s) which are already in the state '.
          '"%s", indicating that they have previously landed:',
          phutil_count($closed),
          $example_ref->getStatusDisplayName()));

      foreach ($closed as $revision_ref) {
        echo tsprintf('%s', $revision_ref->newDisplayRef());
      }

      $query = pht(
        'Land %s revision(s) that are already closed?',
        phutil_count($closed));

      $this->getWorkflow()
        ->getPrompt('arc.land.closed')
        ->setQuery($query)
        ->execute();
    }

    if ($not_accepted) {
      $example_ref = head($not_accepted);

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('%s REVISION(S) ARE NOT ACCEPTED', phutil_count($not_accepted)),
        pht(
          'You are landing %s revision(s) which are not in state "Accepted", '.
          'indicating that they have not been accepted by reviewers. '.
          'Normally, you should land changes only once they have been '.
          'accepted. These revisions are in the wrong state:',
          phutil_count($not_accepted)));

      foreach ($not_accepted as $revision_ref) {
        $display_ref = $revision_ref->newDisplayRef();
        $display_ref->appendLine(
          pht(
            'Status: %s',
            $revision_ref->getStatusDisplayName()));
        echo tsprintf('%s', $display_ref);
      }

      $query = pht(
        'Land %s revision(s) in the wrong state?',
        phutil_count($not_accepted));

      $this->getWorkflow()
        ->getPrompt('arc.land.not-accepted')
        ->setQuery($query)
        ->execute();
    }

    $this->getWorkflow()->loadHardpoints(
      $revision_refs,
      array(
        ArcanistRevisionRef::HARDPOINT_PARENTREVISIONREFS,
      ));

    $open_parents = array();
    foreach ($revision_refs as $revision_phid => $revision_ref) {
      $parent_refs = $revision_ref->getParentRevisionRefs();
      foreach ($parent_refs as $parent_ref) {
        $parent_phid = $parent_ref->getPHID();

        // If we're landing a parent revision in this operation, we don't need
        // to complain that it hasn't been closed yet.
        if (isset($revision_refs[$parent_phid])) {
          continue;
        }

        if ($parent_ref->isClosed()) {
          continue;
        }

        if (!isset($open_parents[$parent_phid])) {
          $open_parents[$parent_phid] = array(
            'ref' => $parent_ref,
            'children' => array(),
          );
        }

        $open_parents[$parent_phid]['children'][] = $revision_ref;
      }
    }

    if ($open_parents) {
      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('%s OPEN PARENT REVISION(S) ', phutil_count($open_parents)),
        pht(
          'The changes you are landing depend on %s open parent revision(s). '.
          'Usually, you should land parent revisions before landing the '.
          'changes which depend on them. These parent revisions are open:',
          phutil_count($open_parents)));

      foreach ($open_parents as $parent_phid => $spec) {
        $parent_ref = $spec['ref'];

        $display_ref = $parent_ref->newDisplayRef();

        $display_ref->appendLine(
          pht(
            'Status: %s',
            $parent_ref->getStatusDisplayName()));

        foreach ($spec['children'] as $child_ref) {
          $display_ref->appendLine(
            pht(
              'Parent of: %s %s',
              $child_ref->getMonogram(),
              $child_ref->getName()));
        }

        echo tsprintf('%s', $display_ref);
      }

      $query = pht(
        'Land changes that depend on %s open revision(s)?',
        phutil_count($open_parents));

      $this->getWorkflow()
        ->getPrompt('arc.land.open-parents')
        ->setQuery($query)
        ->execute();
    }

    $this->confirmBuilds($revision_refs);

    // This is a reasonable place to bulk-load the commit messages, which
    // we'll need soon.

    $this->getWorkflow()->loadHardpoints(
      $revision_refs,
      array(
        ArcanistRevisionRef::HARDPOINT_COMMITMESSAGE,
      ));
  }

  private function confirmBuilds(array $revision_refs) {
    assert_instances_of($revision_refs, 'ArcanistRevisionRef');

    $this->getWorkflow()->loadHardpoints(
      $revision_refs,
      array(
        ArcanistRevisionRef::HARDPOINT_BUILDABLEREF,
      ));

    $buildable_refs = array();
    foreach ($revision_refs as $revision_ref) {
      $ref = $revision_ref->getBuildableRef();
      if ($ref) {
        $buildable_refs[] = $ref;
      }
    }

    $this->getWorkflow()->loadHardpoints(
      $buildable_refs,
      array(
        ArcanistBuildableRef::HARDPOINT_BUILDREFS,
      ));

    $build_refs = array();
    foreach ($buildable_refs as $buildable_ref) {
      foreach ($buildable_ref->getBuildRefs() as $build_ref) {
        $build_refs[] = $build_ref;
      }
    }

    $this->getWorkflow()->loadHardpoints(
      $build_refs,
      array(
        ArcanistBuildRef::HARDPOINT_BUILDPLANREF,
      ));

    $problem_builds = array();
    $has_failures = false;
    $has_ongoing = false;

    $build_refs = msortv($build_refs, 'getStatusSortVector');
    foreach ($build_refs as $build_ref) {
      $plan_ref = $build_ref->getBuildPlanRef();
      if (!$plan_ref) {
        continue;
      }

      $plan_behavior = $plan_ref->getBehavior('arc-land', 'always');
      $if_building = ($plan_behavior == 'building');
      $if_complete = ($plan_behavior == 'complete');
      $if_never = ($plan_behavior == 'never');

      // If the build plan "Never" warns when landing, skip it.
      if ($if_never) {
        continue;
      }

      // If the build plan warns when landing "If Complete" but the build is
      // not complete, skip it.
      if ($if_complete && !$build_ref->isComplete()) {
        continue;
      }

      // If the build plan warns when landing "If Building" but the build is
      // complete, skip it.
      if ($if_building && $build_ref->isComplete()) {
        continue;
      }

      // Ignore passing builds.
      if ($build_ref->isPassed()) {
        continue;
      }

      if ($build_ref->isComplete()) {
        $has_failures = true;
      } else {
        $has_ongoing = true;
      }

      $problem_builds[] = $build_ref;
    }

    if (!$problem_builds) {
      return;
    }

    $build_map = array();
    $failure_map = array();
    $buildable_map = mpull($buildable_refs, null, 'getPHID');
    $revision_map = mpull($revision_refs, null, 'getDiffPHID');
    foreach ($problem_builds as $build_ref) {
      $buildable_phid = $build_ref->getBuildablePHID();
      $buildable_ref = $buildable_map[$buildable_phid];

      $object_phid = $buildable_ref->getObjectPHID();
      $revision_ref = $revision_map[$object_phid];

      $revision_phid = $revision_ref->getPHID();

      if (!isset($build_map[$revision_phid])) {
        $build_map[$revision_phid] = array(
          'revisionRef' => $revision_phid,
          'buildRefs' => array(),
        );
      }

      $build_map[$revision_phid]['buildRefs'][] = $build_ref;
    }

    $log = $this->getLogEngine();

    if ($has_failures) {
      if ($has_ongoing) {
        $message = pht(
          '%s revision(s) have build failures or ongoing builds:',
          phutil_count($build_map));

        $query = pht(
          'Land %s revision(s) anyway, despite ongoing and failed builds?',
          phutil_count($build_map));

      } else {
        $message = pht(
          '%s revision(s) have build failures:',
          phutil_count($build_map));

        $query = pht(
          'Land %s revision(s) anyway, despite failed builds?',
          phutil_count($build_map));
      }

      echo tsprintf(
        "%!\n%s\n\n",
        pht('BUILD FAILURES'),
        $message);

      $prompt_key = 'arc.land.failed-builds';
    } else if ($has_ongoing) {
      echo tsprintf(
        "%!\n%s\n\n",
        pht('ONGOING BUILDS'),
        pht(
          '%s revision(s) have ongoing builds:',
          phutil_count($build_map)));

      $query = pht(
        'Land %s revision(s) anyway, despite ongoing builds?',
        phutil_count($build_map));

      $prompt_key = 'arc.land.ongoing-builds';
    }

    echo tsprintf("\n");
    foreach ($build_map as $build_item) {
      $revision_ref = $build_item['revisionRef'];

      echo tsprintf('%s', $revision_ref->newDisplayRef());

      foreach ($build_item['buildRefs'] as $build_ref) {
        echo tsprintf('%s', $build_ref->newDisplayRef());
      }

      echo tsprintf("\n");
    }

    echo tsprintf(
      "\n%s\n\n",
      pht('You can review build details here:'));

    // TODO: Only show buildables with problem builds.

    foreach ($buildable_refs as $buildable) {
      $display_ref = $buildable->newDisplayRef();

      // TODO: Include URI here.

      echo tsprintf('%s', $display_ref);
    }

    $this->getWorkflow()
      ->getPrompt($prompt_key)
      ->setQuery($query)
      ->execute();
  }

  final protected function confirmImplicitCommits(array $sets, array $symbols) {
    assert_instances_of($sets, 'ArcanistLandCommitSet');
    assert_instances_of($symbols, 'ArcanistLandSymbol');

    $implicit = array();
    foreach ($sets as $set) {
      if ($set->hasImplicitCommits()) {
        $implicit[] = $set;
      }
    }

    if (!$implicit) {
      return;
    }

    echo tsprintf(
      "\n%!\n%W\n",
      pht('IMPLICIT COMMITS'),
      pht(
        'Some commits reachable from the specified sources (%s) are not '.
        'associated with revisions, and may not have been reviewed. These '.
        'commits will be landed as though they belong to the nearest '.
        'ancestor revision:',
        $this->getDisplaySymbols($symbols)));

    foreach ($implicit as $set) {
      $this->printCommitSet($set);
    }

    $query = pht(
      'Continue with this mapping between commits and revisions?');

    $this->getWorkflow()
      ->getPrompt('arc.land.implicit')
      ->setQuery($query)
      ->execute();
  }

  final protected function getDisplaySymbols(array $symbols) {
    $display = array();

    foreach ($symbols as $symbol) {
      $display[] = sprintf('"%s"', addcslashes($symbol->getSymbol(), '\\"'));
    }

    return implode(', ', $display);
  }

  final protected function printCommitSet(ArcanistLandCommitSet $set) {
    $revision_ref = $set->getRevisionRef();

    echo tsprintf(
      "\n%s",
      $revision_ref->newDisplayRef());

    foreach ($set->getCommits() as $commit) {
      $is_implicit = $commit->getIsImplicitCommit();

      $display_hash = $this->getDisplayHash($commit->getHash());
      $display_summary = $commit->getDisplaySummary();

      if ($is_implicit) {
        echo tsprintf(
          "       <bg:yellow> %s </bg> %s\n",
          $display_hash,
          $display_summary);
      } else {
        echo tsprintf(
          "        %s  %s\n",
          $display_hash,
          $display_summary);
      }
    }
  }

  final protected function loadRevisionRefs(array $commit_map) {
    assert_instances_of($commit_map, 'ArcanistLandCommit');
    $workflow = $this->getWorkflow();

    $state_refs = array();
    foreach ($commit_map as $commit) {
      $hash = $commit->getHash();

      $commit_ref = id(new ArcanistCommitRef())
        ->setCommitHash($hash);

      $state_ref = id(new ArcanistWorkingCopyStateRef())
        ->setCommitRef($commit_ref);

      $state_refs[$hash] = $state_ref;
    }

    $force_symbol_ref = $this->getRevisionSymbolRef();
    $force_ref = null;
    if ($force_symbol_ref) {
      $workflow->loadHardpoints(
        $force_symbol_ref,
        ArcanistSymbolRef::HARDPOINT_OBJECT);

      $force_ref = $force_symbol_ref->getObject();
      if (!$force_ref) {
        throw new PhutilArgumentUsageException(
          pht(
            'Symbol "%s" does not identify a valid revision.',
            $force_symbol_ref->getSymbol()));
      }
    }

    $workflow->loadHardpoints(
      $state_refs,
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS);

    foreach ($commit_map as $commit) {
      $hash = $commit->getHash();
      $state_ref = $state_refs[$hash];

      $revision_refs = $state_ref->getRevisionRefs();

      // If we have several possible revisions but one of them matches the
      // "--revision" argument, just select it. This is relatively safe and
      // reasonable and doesn't need a warning.

      if ($force_ref) {
        if (count($revision_refs) > 1) {
          foreach ($revision_refs as $revision_ref) {
            if ($revision_ref->getPHID() === $force_ref->getPHID()) {
              $revision_refs = array($revision_ref);
              break;
            }
          }
        }
      }

      if (count($revision_refs) === 1) {
        $revision_ref = head($revision_refs);
        $commit->setExplicitRevisionRef($revision_ref);
        continue;
      }

      if (!$revision_refs) {
        continue;
      }

      // TODO: If we have several refs but all but one are abandoned or closed
      // or authored by someone else, we could guess what you mean.

      $symbols = $commit->getSymbols();
      $raw_symbols = mpull($symbols, 'getSymbol');
      $symbol_list = implode(', ', $raw_symbols);
      $display_hash = $this->getDisplayHash($hash);

      // TODO: Include "use 'arc look --type commit abc' to figure out why"
      // once that works?

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('AMBIGUOUS REVISION'),
        pht(
          'The revision associated with commit "%s" (an ancestor of: %s) '.
          'is ambiguous. These %s revision(s) are associated with the commit:',
          $display_hash,
          implode(', ', $raw_symbols),
          phutil_count($revision_refs)));

      foreach ($revision_refs as $revision_ref) {
        echo tsprintf(
          '%s',
          $revision_ref->newDisplayRef());
      }

      echo tsprintf("\n");

      throw new PhutilArgumentUsageException(
        pht(
          'Revision for commit "%s" is ambiguous. Use "--revision" to force '.
          'selection of a particular revision.',
          $display_hash));
    }

    // TODO: Some of the revisions we've identified may be mapped to an
    // outdated set of commits. We should look in local branches for a better
    // set of commits, and try to confirm that the state we're about to land
    // is the current state in Differential.

    if ($force_ref) {
      $phid_map = array();
      foreach ($commit_map as $commit) {
        $explicit_ref = $commit->getExplicitRevisionRef();
        if ($explicit_ref) {
          $revision_phid = $explicit_ref->getPHID();
          $phid_map[$revision_phid] = $revision_phid;
        }
      }

      $force_phid = $force_ref->getPHID();

      // If none of the commits have a revision, forcing the revision is
      // reasonable and we don't need to confirm it.

      // If some of the commits have a revision, but it's the same as the
      // revision we're forcing, forcing the revision is also reasonable.

      // Discard the revision we're trying to force, then check if there's
      // anything left. If some of the commits have a different revision,
      // make sure the user is really doing what they expect.

      unset($phid_map[$force_phid]);

      if ($phid_map) {
        // TODO: Make this more clear.

        throw new PhutilArgumentUsageException(
          pht(
            'TODO: You are forcing a revision, but commits are associated '.
            'with some other revision. Are you REALLY sure you want to land '.
            'ALL these commits wiht a different unrelated revision???'));
      }

      foreach ($commit_map as $commit) {
        $commit->setExplicitRevisionRef($force_ref);
      }
    }
  }

  final protected function getDisplayHash($hash) {
    // TODO: This should be on the API object.
    return substr($hash, 0, 12);
  }

  final protected function confirmCommits(
    $into_commit,
    array $symbols,
    array $commit_map) {

    $commit_count = count($commit_map);

    if (!$commit_count) {
      $message = pht(
        'There are no commits reachable from the specified sources (%s) '.
        'which are not already present in the state you are merging '.
        'into ("%s"), so nothing can land.',
        $this->getDisplaySymbols($symbols),
        $this->getDisplayHash($into_commit));

      echo tsprintf(
        "\n%!\n%W\n\n",
        pht('NOTHING TO LAND'),
        $message);

      throw new PhutilArgumentUsageException(
        pht('There are no commits to land.'));
    }

    // Reverse the commit list so that it's oldest-first, since this is the
    // order we'll use to show revisions.
    $commit_map = array_reverse($commit_map, true);

    $warn_limit = $this->getWorkflow()->getLargeWorkingSetLimit();
    $show_limit = 5;
    if ($commit_count > $warn_limit) {
      if ($into_commit === null) {
        $message = pht(
          'There are %s commit(s) reachable from the specified sources (%s). '.
          'You are landing into the empty state, so all of these commits '.
          'will land:',
          new PhutilNumber($commit_count),
          $this->getDisplaySymbols($symbols));
      } else {
        $message = pht(
          'There are %s commit(s) reachable from the specified sources (%s) '.
          'that are not present in the repository state you are merging '.
          'into ("%s"). All of these commits will land:',
          new PhutilNumber($commit_count),
          $this->getDisplaySymbols($symbols),
          $this->getDisplayHash($into_commit));
      }

      echo tsprintf(
        "\n%!\n%W\n",
        pht('LARGE WORKING SET'),
        $message);

      $display_commits = array_merge(
        array_slice($commit_map, 0, $show_limit),
        array(null),
        array_slice($commit_map, -$show_limit));

      echo tsprintf("\n");

      foreach ($display_commits as $commit) {
        if ($commit === null) {
          echo tsprintf(
            "  %s\n",
            pht(
              '< ... %s more commits ... >',
              new PhutilNumber($commit_count - ($show_limit * 2))));
        } else {
          echo tsprintf(
            "  %s %s\n",
            $this->getDisplayHash($commit->getHash()),
            $commit->getDisplaySummary());
        }
      }

      $query = pht(
        'Land %s commit(s)?',
        new PhutilNumber($commit_count));

      $this->getWorkflow()
        ->getPrompt('arc.land.large-working-set')
        ->setQuery($query)
        ->execute();
    }

    // Build the commit objects into a tree.
    foreach ($commit_map as $commit_hash => $commit) {
      $parent_map = array();
      foreach ($commit->getParents() as $parent) {
        if (isset($commit_map[$parent])) {
          $parent_map[$parent] = $commit_map[$parent];
        }
      }
      $commit->setParentCommits($parent_map);
    }

    // Identify the commits which are heads (have no children).
    $child_map = array();
    foreach ($commit_map as $commit_hash => $commit) {
      foreach ($commit->getParents() as $parent) {
        $child_map[$parent][$commit_hash] = $commit;
      }
    }

    foreach ($commit_map as $commit_hash => $commit) {
      if (isset($child_map[$commit_hash])) {
        continue;
      }
      $commit->setIsHeadCommit(true);
    }

    return $commit_map;
  }

  public function execute() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $this->validateArguments();

    $raw_symbols = $this->getSourceRefs();
    if (!$raw_symbols) {
      $raw_symbols = $this->getDefaultSymbols();
    }

    $symbols = array();
    foreach ($raw_symbols as $raw_symbol) {
      $symbols[] = id(new ArcanistLandSymbol())
        ->setSymbol($raw_symbol);
    }

    $this->resolveSymbols($symbols);

    $onto_remote = $this->selectOntoRemote($symbols);
    $this->setOntoRemote($onto_remote);

    $onto_refs = $this->selectOntoRefs($symbols);
    $this->confirmOntoRefs($onto_refs);
    $this->setOntoRefs($onto_refs);

    $this->selectIntoRemote();
    $this->selectIntoRef();

    $into_commit = $this->selectIntoCommit();
    $commit_map = $this->selectCommits($into_commit, $symbols);

    $this->loadRevisionRefs($commit_map);

    // TODO: It's possible we have a list of commits which includes disjoint
    // groups of commits associated with the same revision, or groups of
    // commits which do not form a range. We should test that here, since we
    // can't land commit groups which are not a single contiguous range.

    $revision_groups = array();
    foreach ($commit_map as $commit_hash => $commit) {
      $revision_ref = $commit->getRevisionRef();

      if (!$revision_ref) {
        echo tsprintf(
          "\n%!\n%W\n\n",
          pht('UNKNOWN REVISION'),
          pht(
            'Unable to determine which revision is associated with commit '.
            '"%s". Use "arc diff" to create or update a revision with this '.
            'commit, or "--revision" to force selection of a particular '.
            'revision.',
            $this->getDisplayHash($commit_hash)));

        throw new PhutilArgumentUsageException(
          pht(
            'Unable to determine revision for commit "%s".',
            $this->getDisplayHash($commit_hash)));
      }

      $revision_groups[$revision_ref->getPHID()][] = $commit;
    }

    $commit_heads = array();
    foreach ($commit_map as $commit) {
      if ($commit->getIsHeadCommit()) {
        $commit_heads[] = $commit;
      }
    }

    $revision_order = array();
    foreach ($commit_heads as $head) {
      foreach ($head->getAncestorRevisionPHIDs() as $phid) {
        $revision_order[$phid] = true;
      }
    }

    $revision_groups = array_select_keys(
      $revision_groups,
      array_keys($revision_order));

    $sets = array();
    foreach ($revision_groups as $revision_phid => $group) {
      $revision_ref = head($group)->getRevisionRef();

      $set = id(new ArcanistLandCommitSet())
        ->setRevisionRef($revision_ref)
        ->setCommits($group);

      $sets[$revision_phid] = $set;
    }

    if (!$this->getShouldPreview()) {
      $this->confirmImplicitCommits($sets, $symbols);
    }

    $log->writeStatus(
      pht('LANDING'),
      pht('These changes will land:'));

    foreach ($sets as $set) {
      $this->printCommitSet($set);
    }

    if ($this->getShouldPreview()) {
      $log->writeStatus(
        pht('PREVIEW'),
        pht('Completed preview of land operation.'));
      return;
    }

    $query = pht('Land these changes?');
    $this->getWorkflow()
      ->getPrompt('arc.land.confirm')
      ->setQuery($query)
      ->execute();

    $this->confirmRevisions($sets);

    $workflow = $this->getWorkflow();

    $is_incremental = $this->getIsIncremental();
    $is_hold = $this->getShouldHold();
    $is_keep = $this->getShouldKeep();

    $local_state = $api->newLocalState()
      ->setWorkflow($workflow)
      ->saveLocalState();

    $seen_into = array();
    try {
      $last_key = last_key($sets);

      $need_cascade = array();
      $need_prune = array();

      foreach ($sets as $set_key => $set) {
        // Add these first, so we don't add them multiple times if we need
        // to retry a push.
        $need_prune[] = $set;
        $need_cascade[] = $set;

        while (true) {
          $into_commit = $this->executeMerge($set, $into_commit);

          if ($is_hold) {
            $should_push = false;
          } else if ($is_incremental) {
            $should_push = true;
          } else {
            $is_last = ($set_key === $last_key);
            $should_push = $is_last;
          }

          if ($should_push) {
            try {
              $this->pushChange($into_commit);
            } catch (Exception $ex) {

              // TODO: If the push fails, fetch and retry if the remote ref
              // has moved ahead of us.

              if ($this->getIntoLocal()) {
                $can_retry = false;
              } else if ($this->getIntoEmpty()) {
                $can_retry = false;
              } else if ($this->getIntoRemote() !== $this->getOntoRemote()) {
                $can_retry = false;
              } else {
                $can_retry = false;
              }

              if ($can_retry) {
                // New commit state here
                $into_commit = '..';
                continue;
              }

              throw $ex;
            }

            if ($need_cascade) {

              // NOTE: We cascade each set we've pushed, but we're going to
              // cascade them from most recent to least recent. This way,
              // branches which descend from more recent changes only cascade
              // once, directly in to the correct state.

              $need_cascade = array_reverse($need_cascade);
              foreach ($need_cascade as $cascade_set) {
                $this->cascadeState($set, $into_commit);
              }
              $need_cascade = array();
            }

            if (!$is_keep) {
              $this->pruneBranches($need_prune);
              $need_prune = array();
            }
          }

          break;
        }
      }

      if ($is_hold) {
        $this->didHoldChanges();
        $this->discardLocalState();
      } else {
        $this->reconcileLocalState($into_commit, $local_state);
      }

      // TODO: Restore this.
      // $this->getWorkflow()->askForRepositoryUpdate();

      $log->writeSuccess(
        pht('DONE'),
        pht('Landed changes.'));
    } catch (Exception $ex) {
      $local_state->restoreLocalState();
      throw $ex;
    } catch (Throwable $ex) {
      $local_state->restoreLocalState();
      throw $ex;
    }
  }


  protected function validateArguments() {
    $log = $this->getLogEngine();

    $into_local = $this->getIntoLocalArgument();
    $into_empty = $this->getIntoEmptyArgument();
    $into_remote = $this->getIntoRemoteArgument();

    $into_count = 0;
    if ($into_remote !== null) {
      $into_count++;
    }

    if ($into_local) {
      $into_count++;
    }

    if ($into_empty) {
      $into_count++;
    }

    if ($into_count > 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Arguments "--into-local", "--into-remote", and "--into-empty" '.
          'are mutually exclusive.'));
    }

    $into = $this->getIntoArgument();
    if ($into && ($into_empty !== null)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Arguments "--into" and "--into-empty" are mutually exclusive.'));
    }

    $strategy = $this->selectMergeStrategy();
    $this->setStrategy($strategy);

    // Build the symbol ref here (which validates the format of the symbol),
    // but don't load the object until later on when we're sure we actually
    // need it, since loading it requires a relatively expensive Conduit call.
    $revision_symbol = $this->getRevisionSymbol();
    if ($revision_symbol) {
      $symbol_ref = id(new ArcanistRevisionSymbolRef())
        ->setSymbol($revision_symbol);
      $this->setRevisionSymbolRef($symbol_ref);
    }

    // NOTE: When a user provides: "--hold" or "--preview"; and "--incremental"
    // or various combinations of remote flags, the flags affecting push/remote
    // behavior have no effect.

    // These combinations are allowed to support adding "--preview" or "--hold"
    // to any command to run the same command with fewer side effects.
  }

  abstract protected function getDefaultSymbols();
  abstract protected function resolveSymbols(array $symbols);
  abstract protected function selectOntoRemote(array $symbols);
  abstract protected function selectOntoRefs(array $symbols);
  abstract protected function confirmOntoRefs(array $onto_refs);
  abstract protected function selectIntoRemote();
  abstract protected function selectIntoRef();
  abstract protected function selectIntoCommit();
  abstract protected function selectCommits($into_commit, array $symbols);
  abstract protected function executeMerge(
    ArcanistLandCommitSet $set,
    $into_commit);
  abstract protected function pushChange($into_commit);
  abstract protected function cascadeState(
    ArcanistLandCommitSet $set,
    $into_commit);

  protected function isSquashStrategy() {
    return ($this->getStrategy() === 'squash');
  }

  abstract protected function pruneBranches(array $sets);

  abstract protected function reconcileLocalState(
    $into_commit,
    ArcanistRepositoryLocalState $state);

  private function selectMergeStrategy() {
    $log = $this->getLogEngine();

    $supported_strategies = array(
      'merge',
      'squash',
    );
    $supported_strategies = array_fuse($supported_strategies);
    $strategy_list = implode(', ', $supported_strategies);

    $strategy = $this->getStrategyArgument();
    if ($strategy !== null) {
      if (!isset($supported_strategies[$strategy])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Merge strategy "%s" specified with "--strategy" is unknown. '.
            'Supported merge strategies are: %s.',
            $strategy,
            $strategy_list));
      }

      $log->writeStatus(
        pht('STRATEGY'),
        pht(
          'Merging with "%s" strategy, selected with "--strategy".',
          $strategy));

      return $strategy;
    }

    $strategy_key = 'arc.land.strategy';
    $strategy = $this->getWorkflow()->getConfig($strategy_key);
    if ($strategy !== null) {
      if (!isset($supported_strategies[$strategy])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Merge strategy "%s" specified in "%s" configuration is '.
            'unknown. Supported merge strategies are: %s.',
            $strategy,
            $strategy_list));
      }

      $log->writeStatus(
        pht('STRATEGY'),
        pht(
          'Merging with "%s" strategy, configured with "%s".',
          $strategy,
          $strategy_key));

      return $strategy;
    }

    $strategy = 'squash';

    $log->writeStatus(
      pht('STRATEGY'),
      pht(
        'Merging with "%s" strategy, the default strategy.',
        $strategy));

    return $strategy;
  }

}
