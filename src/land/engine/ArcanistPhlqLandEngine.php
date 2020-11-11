<?php

class ArcanistPhlqLandEngine extends ArcanistGitLandEngine {
  protected $phlqUrl;
  protected $phlqLogsPath = "/log/D";
  protected $phlqLandPath = "/land/D";
  protected $phlqStatePath = "/state/D";

  function __construct($phlq_url) {
    if(empty($phlq_url))
      throw new PhutilArgumentUsageException(
        pht('PHLQ url not found. Set %s in your .arcconfig.', 'phlq.url')
      );

    $this->phlqUrl = $phlq_url;
  }

  function landRevision($rev_id, $remote_url) {
    $handle = curl_init($this->phlqUrl . $this->phlqLandPath . $rev_id);
    $data = array('repo_url' => $remote_url);
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

  function landState($rev_id) {
    $url = $this->phlqUrl . $this->phlqStatePath . $rev_id;
    return trim(file_get_contents($url));
  }

  function landLogs($rev_id) {
    $url = $this->phlqUrl . $this->phlqLogsPath . $rev_id;
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

    foreach ($revision_ids as $rev_id) {
      $remote_url = $api->getRemoteUrl();
      $this->landRevision($rev_id, $remote_url);
      $message = "Land request send. Landing logs: ".$this->phlqUrl . $this->phlqLogsPath . $rev_id;
      $log->writeSuccess(
        pht('DONE'),
        pht($message));
    }
 
    $landing_errors = $this->tailLogs($revision_ids, $log);
    if($landing_errors == 0)
      $this->cleanTags($api, $log);
  }

  function cleanTags($api, $log) {
    $log->writeStatus(
      pht('CLEANUP'),
      pht('Cleaning tags.'));

    $api->cleanTags();
  }

  function tailLogs($revision_ids, $log) {
    $tries = 0;
    $landing_errors = 0;
    $last_state = [];
    $tail_position = [];
    foreach ($revision_ids as $rev_id)
      $tail_position[$rev_id] = 0;

    while($tail_position) {
      foreach(array_keys($tail_position) as $rev_id) {
        $land_state = $this->landState($rev_id);
        $land_logs = $this->landLogs($rev_id);
        $out = substr($land_logs, $tail_position[$rev_id]);
        $tail_position[$rev_id] = strlen($land_logs);

        print($out);

        if($land_state == "DONE" || $land_state == "ERROR") {
          unset($tail_position[$rev_id]);

          $msg = "D".$rev_id.": ".$land_state;
          if($land_state == "DONE")
            $log->writeSuccess(pht("LANDING"), pht($msg));

          if($land_state == "ERROR") {
            $log->writeError(pht("LANDING"), pht($msg));
            $landing_errors++;
          }

        } else if($tries % 10 == 0) {
          $log->writeStatus(pht("LANDING"), pht("D".$rev_id.": ".$land_state));
        } else if($last_state[$rev_id] && $land_state != $last_state[$rev_id]) {
          $log->writeStatus(pht("LANDING"), pht("D".$rev_id.": ".$land_state));
        }
        $last_state[$rev_id] = $land_state;
      }
      $tries += 1;
      if($tail_position) sleep(4);
    }
    return $landing_errors;
  }
}
