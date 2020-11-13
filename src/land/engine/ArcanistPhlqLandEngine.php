<?php

class ArcanistPhlqLandEngine extends ArcanistGitLandEngine {
  protected $phlqLogsPath = "/log/";
  protected $phlqLandPath = "/land/stack";
  protected $phlqStatePath = "/state/";

  function landRevisions($phlq_uri, $revision_ids, $remote_url) {
    $handle = curl_init($phlq_uri . $this->phlqLandPath);
    $data = array(
      'repo_url' => $remote_url,
      'revisions' => array_map(function ($r) { return "D".$r; }, $revision_ids),
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
    $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if($httpCode == 404)
      return null;

    return $response;
  }

  function landState($phlq_uri, $log_id) {
    $url = $phlq_uri . $this->phlqStatePath . $log_id;
    return trim(file_get_contents($url));
  }

  function landLogs($phlq_uri, $log_id) {
    $url = $phlq_uri . $this->phlqLogsPath . $log_id;
    return file_get_contents($url);
  }

  function execute() {
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

      $set = id(new ArcanistLandCommitSet())
        ->setRevisionRef($revision_ref)
        ->setCommits($group);

      $sets[$revision_phid] = $set;
    }

    $sets = $this->filterCommitSets($sets);

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

    $phlq_uri = $this->getWorkflow()->getPhlqUri();

    $log_id = "D" . $revision_ids[0];
    if (count($revision_ids) > 1) {
      $log_id = "D" . end($revision_ids);
    }
    $remote_url = $api->getRemoteUrl();
    try {
      $log_tail_start = strlen($this->landLogs($phlq_uri, $log_id));
    } catch (Exception $e) {
      $log_tail_start = 0;
    }
    $this->landRevisions($phlq_uri, $revision_ids, $remote_url);
    $message = "Land request sent. Landing logs: ". $phlq_uri . $this->phlqLogsPath . $log_id;
    $log->writeSuccess(
      pht('DONE'),
      pht($message));

    $success = $this->tailLogs($phlq_uri, $log_id, $log_tail_start, $log);
    if($success)
      $this->cleanupAfterLand($api, $log);
  }

  function cleanupAfterLand($api, $log) {
    $current_branch = $api->getBranchName();
    $log->writeStatus(
      pht('CLEANUP'),
      pht("Current branch is '%s'", $current_branch));

    $log->writeStatus(
      pht('CLEANUP'),
      pht('Fetching origin master'));
    $api->execxLocal('fetch --no-tags --quiet -- origin master');

    $log->writeStatus(
      pht('CLEANUP'),
      pht('Cleaning tags'));
    $api->execxLocal("fetch --prune origin '+refs/tags/*:refs/tags/*'");

    list($stdout) = $api->execxLocal('status --porcelain');
    $status = trim($stdout);
    if ($status != "") {
      // If there are local changes then we're done
      $log->writeStatus(
        pht('CLEANUP'),
        pht("Branch '%s' has local changes", $current_branch));
      return;
    }

    $log->writeStatus(
      pht('CLEANUP'),
      pht('Rebasing to origin/master'));
    $api->execxLocal('rebase origin/master');

    if ($current_branch != "master") {
      try {
        // Check if the non-master branch has been fully landed
        $log->writeStatus(
          pht('CLEANUP'),
          pht('Checking merge-base'));

        // Will throw on exit code is 1 if current_branch is not an ancestor
        // of origin/master which means it is not fully landed
        $api->execxLocal('merge-base --is-ancestor -- %s origin/master', $current_branch);
        $log->writeStatus(
          pht('CLEANUP'),
          pht("Branch %s is fully landed", $current_branch));
        $fully_landed = true;
      } catch (Exception $e) {
        // If not fully landed then we're done
        $log->writeStatus(
          pht('CLEANUP'),
          pht("Branch '%s' is not fully landed", $current_branch));
          return;
      }

      $log->writeStatus(
        pht('CLEANUP'),
        pht('Switching to master branch'));
      $api->execxLocal('checkout master');

      $log->writeStatus(
        pht('CLEANUP'),
        pht('Rebasing to origin/master'));
      $api->execxLocal('rebase origin/master');
  
      $log->writeStatus(
        pht('CLEANUP'),
        pht("Deleting branch '%s'.", $current_branch));
      $api->execxLocal('branch -D -- %s', $current_branch);
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
        $log->writeStatus(pht("LANDING"), pht($log_id.": ".$land_state));
        return true;
      } else if ($land_state == "ERROR") {
        # Failure
        $log->writeError(pht("LANDING"), pht($log_id.": ".$land_state));
        return false;
      } else if ($count % 10 == 0) {
        # Periodic update
        $log->writeStatus(pht("LANDING"), pht($log_id.": ".$land_state));
      } else if ($land_state != $last_state) {
        # State change
        $log->writeStatus(pht("LANDING"), pht($log_id.": ".$land_state));
      }
      $last_state = $land_state;
      $count += 1;
      sleep(4);
    }
  }
}
