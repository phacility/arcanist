<?php

class ArcanistPhlqLandEngine extends ArcanistGitLandEngine {
  protected $phlqLogsPath = "/log/";
  protected $phlqLandPath = "/land/stack";
  protected $phlqStatePath = "/state/";

  function landRevisions($phlq_uri, $revision_ids, $username) {
    $handle = curl_init($phlq_uri . $this->phlqLandPath);
    $data = array(
      'revisions' => array_map(function ($r) { return "D".$r; }, $revision_ids),
      'username' => $username,
    );
    $data_string = json_encode($data);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($handle, CURLOPT_POST, TRUE);
    curl_setopt($handle, CURLOPT_POSTFIELDS, $data_string);
    curl_setopt($handle, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Content-Length: ' . strlen($data_string))
    );

    $response = curl_exec($handle);
    $error = curl_error($handle);
    if ($error != "") {
      throw new Exception(pht("Request to phlq (%s) failed: %s", $phlq_uri, $error));
    }
    $http_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if ($http_code != 200) {
      throw new Exception(pht(
        "Request to phlq (%s) failed with response %s: %s",
        $phlq_uri,
        $http_code,
        $response));
    }
  }

  function landState($phlq_uri, $log_id) {
    $url = $phlq_uri . $this->phlqStatePath . $log_id;
    return trim(file_get_contents($url));
  }

  function landLogs($phlq_uri, $log_id) {
    $url = $phlq_uri . $this->phlqLogsPath . $log_id;
    return file_get_contents($url);
  }

  function confirmImplicitCommits(array $sets, array $symbols) {
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

    $log = $this->getLogEngine();
    $log->writeError(
      pht('IMPLICIT COMMITS'),
      pht(
        "Some commits reachable from the specified sources (%s) are not " .
        "associated with revisions and may not have been reviewed. ",
        $this->getDisplaySymbols($symbols)));
    throw new ArcanistRevisionStatusException(
      "All commits must be associated with revisions to land with land queue. " .
      "Land queue is NOT aware of local changes that have not been pushed to the revision and " .
      "will land the latest diff in the revision. " .
      "Run 'arc diff' before landing.");
  }

  function execute() {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();
    $workflow = $this->getWorkflow();
    $conduitEngine = $workflow->getConduitEngine();

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

    $revision_ids = array();
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
            $api->getDisplayHash($commit_hash)));

        throw new PhutilArgumentUsageException(
          pht(
            'Unable to determine revision for commit "%s".',
            $api->getDisplayHash($commit_hash)));
      }

      $revision_groups[$revision_ref->getPHID()][] = $commit;
      array_push($revision_ids, $revision_ref->getID());
    }
    $revision_ids = array_unique($revision_ids);

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
      $revision_id = $revision_ref->getID();

      $set = id(new ArcanistLandCommitSet())
        ->setRevisionRef($revision_ref)
        ->setCommits($group);

      $sets[$revision_phid] = $set;
    }

    $sets = $this->filterCommitSets($sets);

    if (!$this->getShouldPreview()) {
      $this->confirmImplicitCommits($sets, $symbols);
    }

    foreach ($revision_groups as $revision_phid => $group) {
      $revision_ref = head($group)->getRevisionRef();
      $revision_id = $revision_ref->getID();

      # get the last modified time for the revision diff
      $local_timestamp_warning = false;
      $future = $conduitEngine->newFuture(
        "differential.revision.search",
        array(
          'constraints' => array('phids' => [$revision_phid]),
        )
      );
      $response = $future->resolve();
      $diff_phid = $response['data'][0]['fields']['diffPHID'];
      $future = $conduitEngine->newFuture(
        "differential.diff.search",
        array(
          'constraints' => array('phids' => [$diff_phid]),
        )
      );
      $response = $future->resolve();
      $diff_date_modified = $response['data'][0]['fields']['dateModified'];

      # compare against the unix commiter timestamps for each commit
      foreach ($group as $commit) {
        $git_cmd = "show -s --format=%%ct " . $commit->getHash();
        list($err, $commit_unix_date) = $api->execManualLocal($git_cmd);
        if ($err) {
          throw new Exception(pht("git command '%s' failed with exit code %s", $git_cmd, strval($err)));
        }
        $commit_unix_date = intval(trim($commit_unix_date));
        $timestamp_diff = $commit_unix_date - $diff_date_modified;
        # allow for small theshold before tripping the warning
        if ($timestamp_diff > 5) {
          $pretty_diff = sprintf('%02dh %02dm %02ds', ($timestamp_diff/3600),($timestamp_diff/60%60), $timestamp_diff%60);
          echo tsprintf(
            "\n%!\n%W\n",
            pht('POTENTIAL UNPUSHED LOCAL CHANGES FOR D%s', $revision_id),
            pht(
              "Local commit %s (%s) in revision D%s has a timestamp that is " .
              "%s ahead of the revision's latest diff timestamp.\n\n" .
              "Land queue is NOT aware of local changes that have not been pushed to the revision and " .
              "will land the latest diff in revision D%s.\n\n" .
              "If you have rebased to resolve merge conflicts or made other local changes that have not been pushed to " .
              "the revision, you must call 'arc diff' again before landing.\n\n" .
              "If you are landing after an 'arc patch' and have not made any local changes then " .
              "you may ignore this warning. Local timestamps are expected to be newer after patching.\n\n",
              $commit->getHash(),
              $commit->getSummary(),
              $revision_id,
              $pretty_diff,
              $revision_id));

          $query = pht('Ignore potential local changes for D%s?', $revision_id);

          $this->getWorkflow()
            ->getPrompt('arc.land.confirm-timestamps')
            ->setQuery($query)
            ->execute();  
        }
      }
    }

    # get phabricator user name
    $future = $conduitEngine->newFuture("user.whoami", array());
    $response = $future->resolve();
    $username = $response['userName'];
    if (!$username) {
      throw new Exception("Failed to determine username from conduit");
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

    $is_incremental = $this->getIsIncremental();
    $is_hold = $this->getShouldHold();
    $is_keep = $this->getShouldKeep();

    $local_state = $api->newLocalState()
      ->setWorkflow($workflow)
      ->saveLocalState();

    $this->setLocalState($local_state);

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
          $this->setHasUnpushedChanges(true);

          if ($is_hold) {
            $should_push = false;
          } else if ($is_incremental) {
            $should_push = true;
          } else {
            $is_last = ($set_key === $last_key);
            $should_push = $is_last;
          }

          if ($should_push) {
            // Instead of pushing, make a request to land queue
            $phlq_uri = $this->getWorkflow()->getPhlqUri();

            $log_id = "D" . $revision_ids[0];
            if (count($revision_ids) > 1) {
              $log_id = $log_id . "-D" . end($revision_ids);
            }
            try {
              $log_tail_start = strlen($this->landLogs($phlq_uri, $log_id));
            } catch (Exception $e) {
              $log_tail_start = 0;
            }
            try {
              $response_code = $this->landRevisions($phlq_uri, $revision_ids, $username);
            } catch (Exception $e) {
              $log->writeError(
                pht('LAND QUEUE'),
                pht("Exception: %s", $e->getMessage()));
              throw $e;
            }
            $logs_uri = $phlq_uri . $this->phlqLogsPath . $log_id;
            $message = "Land request sent. Landing logs: " . $logs_uri;
            $log->writeSuccess(
              pht('LAND QUEUE'),
              pht($message));

            $success = $this->tailLogs($phlq_uri, $log_id, $log_tail_start, $log);
            if ($success) {
              // Since we didn't push locally, we fetch after remote land successful
              $this->fetchAfterLand($api, $log);
              $this->setHasUnpushedChanges(false);
              $local_state->discardLocalState();
            } else {
              throw new Exception("Land failed on land queue " . $logs_uri);
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
        $this->didHoldChanges($into_commit);
        $local_state->discardLocalState();
      } else {
        $this->reconcileLocalState($into_commit, $local_state);

        // Since we didn't push the SHA of the landed commit won't match
        // what would have been pushed from here. We rebase which should
        // fast forward unless something went wrong.
        $this->rebaseIfPristineDefaultBranch($api, $log);

        $log->writeSuccess(
          pht('DONE'),
          pht('Landed changes.'));
      }
    } catch (Exception $ex) {
      $local_state->restoreLocalState();
      throw $ex;
    } catch (Throwable $ex) {
      $local_state->restoreLocalState();
      throw $ex;
    }
  }

  function fetchAfterLand($api, $log) {
    $log->writeStatus(
      pht('CLEANUP'),
      pht('Fetching origin master'));
    $api->execxLocal('fetch --no-tags --quiet -- origin master');

    $log->writeStatus(
      pht('CLEANUP'),
      pht('Cleaning tags'));
    $api->execxLocal("fetch --prune origin '+refs/tags/*:refs/tags/*'");
  }

  function rebaseIfPristineDefaultBranch($api, $log) {
    $current_branch = $api->getBranchName();
    $log->writeStatus(
      pht('CLEANUP'),
      pht("Current branch is '%s'", $current_branch));

    if ($current_branch == "master" || $current_branch == "develop") {
      list($stdout) = $api->execxLocal('status --porcelain');
      $status = trim($stdout);
      if ($status != "") {
        // If there are local changes then we're done
        $log->writeStatus(
          pht('CLEANUP'),
          pht("Branch '%s' has local changes", $current_branch));
        return;
      }
      try {
        $log->writeStatus(
          pht('CLEANUP'),
          pht('Rebasing to origin/' . $current_branch));
        $api->execxLocal('rebase origin/' . $current_branch);
      } catch (Exception $e) {
        $log->writeWarning(
          pht('CLEANUP'),
          pht('Rebase failed'));
        try {
          $log->writeStatus(
            pht('CLEANUP'),
            pht('Aborting rebase'));
          $api->execxLocal('rebase --abort');
        } catch (Exception $e) {}
        return;
      }
    }
  }

  function tailLogs($phlq_uri, $log_id, $log_tail_start, $log) {
    $count = 0;
    $landing_errors = 0;
    $last_state = "";
    $tail_position = $log_tail_start;
    while (true) {
      $land_state = $this->landState($phlq_uri, $log_id);
      $land_logs = $this->landLogs($phlq_uri, $log_id);
      $out = substr($land_logs, $tail_position);
      $tail_position = strlen($land_logs);
      print($out);
      $msg = $log_id.": ".$land_state;
      if ($land_state == "DONE") {
        # Success
        $log->writeStatus(pht("LAND QUEUE"), pht($log_id.": ".$land_state));
        return true;
      } else if ($land_state == "ERROR") {
        # Failure
        $log->writeError(pht("LAND QUEUE"), pht($log_id.": ".$land_state));
        return false;
      } else if ($count % 10 == 0) {
        # Periodic update
        $log->writeStatus(pht("LAND QUEUE"), pht($log_id.": ".$land_state));
      } else if ($land_state != $last_state) {
        # State change
        $log->writeStatus(pht("LAND QUEUE"), pht($log_id.": ".$land_state));
      }
      $last_state = $land_state;
      $count += 1;
      sleep(4);
    }
  }
}
