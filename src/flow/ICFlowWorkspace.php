<?php

final class ICFlowWorkspace extends Phobject {

  private $revisionsLoaded = false;
  private $headDiffsLoaded = false;
  private $activeDiffsLoaded = false;
  private $conduit;
  private $git;
  private $cache;
  private $headRefs;
  private $features;
  private $rootBranch = null;
  private $terminalBranch = null;

  public function setConduit(ConduitClient $conduit) {
    $this->conduit = $conduit;
    return $this;
  }

  public function getConduit() {
    return $this->conduit;
  }

  public function setGitAPI(ICGitAPI $git) {
    $this->git = $git;
    return $this;
  }

  public function setRootBranch($root_branch) {
    $this->rootBranch = $root_branch;
    return $this;
  }

  public function getRootBranch() {
    if ($this->rootBranch == null) {
      $this->setRootBranch($this->getGitAPI->getDefaultRemoteBranch());
    }
    return $this->rootBranch;
  }

  public function getGitAPI() {
    return $this->git;
  }

  public function setCache(PhutilKeyValueCache $cache) {
    $this->cache = $cache;
    return $this;
  }

  public function getCurrentFeature() {
    $features = $this->getFeatures();
    foreach ($features as $feature) {
      $ref = $feature->getHead();
      if ($ref->isHEAD()) {
        return $feature;
      }
    }
    return null;
  }

  public function cacheHeadDiffs(array $shas) {
    $shas = array_unique($shas);
    $keys = array();
    foreach ($shas as $sha) {
      $keys[$sha] = "head-diff-{$sha}";
    }
    $existing_shas = $this->cache->getKeys($keys);
    $rval = array();
    foreach ($keys as $sha => $cache_key) {
      $diff = idx($existing_shas, $cache_key);
      if ($diff === null) {
        $parent_sha = $this->git->getParentSha($sha);
        $diff = $this->git->getAPI()->getFullGitDiff($parent_sha, $sha);
        $this->cache->setKey($cache_key, $diff, 60 * 60 * 24 * 7);
      }
      $rval[$sha] = $diff;
    }
    return $rval;
  }

  private function cacheActiveDiffs(array $diff_ids) {
    $diff_ids = array_unique($diff_ids);
    $keys = array();
    foreach ($diff_ids as $diff_id) {
      $keys[$diff_id] = "active-diff-{$diff_id}";
    }
    $existing_ids = $this->cache->getKeys($keys);
    $rval = array();
    foreach ($keys as $diff_id => $cache_key) {
      $diff = idx($existing_ids, $cache_key);
      if ($diff === null) {
        $diff = $this->conduit->callMethodSynchronous('differential.getrawdiff',
          array(
            'diffID' => $diff_id,
        ));
        $this->cache->setKey($cache_key, $diff, 60 * 60 * 24 * 7);
      }
      $rval[$diff_id] = $diff;
    }
    return $rval;
  }

  public function loadActiveDiffs() {
    if (!$this->activeDiffsLoaded) {
      $this->loadRevisions();
      $diff_ids = array_filter(mpull($this->getFeatures(), 'getActiveDiffID'));
      $active_diffs = $this->cacheActiveDiffs($diff_ids);
      foreach ($this->getFeatures() as $branch => $feature) {
        $diff_id = $feature->getActiveDiffID();
        if (!$diff_id) {
          $feature->attachActiveDiff(null);
          continue;
        }
        $feature->attachActiveDiff(idx($active_diffs, $diff_id));
      }
    }
    return $this;
  }

  public function loadHeadDiffs() {
    if (!$this->headDiffsLoaded) {
      $this->loadRevisions();
      $refs = mpull($this->getFeatures(), 'getHead');
      $sha_groups = mgroup($refs, 'getObjectName');
      $head_diffs = $this->cacheHeadDiffs(array_keys($sha_groups));
      foreach ($sha_groups as $sha => $sha_refs) {
        $diff = idx($head_diffs, $sha);
        foreach ($sha_refs as $ref) {
          $ref->attachHeadDiff($diff);
        }
      }
      $this->headDiffsLoaded = true;
    }
    return $this;
  }

  private function differentialQuerySearchResults(array $ids) {
    if (!$ids) {
      return array(array(), array());
    }
    $conduit = $this->conduit;
    $query_future = $conduit->callMethod('differential.query', array(
        'ids' => $ids,
      ));
    $query_future->start();
    $search_future = $conduit->callMethod('differential.revision.search', array(
        'constraints' => array(
          'ids' => $ids,
        ),
        'attachments' => array(
          'queue-submissions' => true,
        ),
      ));
    $search_future->start();
    $query_results = $query_future->resolve();
    $query_results = ipull($query_results, null, 'id');
    $search_results = $search_future->resolve();
    $search_results = ipull(idx($search_results, 'data'), null, 'id');
    return array($query_results, $search_results);
  }

  public function loadRevisions() {
    if (!$this->revisionsLoaded) {
      $features = $this->getFeatures();
      $revision_features = mfilter($features, 'getRevisionID');
      $ids = array_values(array_unique(mpull(
        $revision_features,
        'getRevisionID')));
      list($query_results, $search_results) =
        $this->differentialQuerySearchResults($ids);
      foreach ($features as $feature) {
        $rev_id = $feature->getRevisionID();
        $rev_data = $rev_id ? idx($query_results, $rev_id, array()) : null;
        $feature->attachRevisionData($rev_data);
        $search_data = $rev_id ? idx($search_results, $rev_id, array()) : null;
        $feature->attachSearchData($search_data);
      }
      $this->revisionsLoaded = true;
    }
    return $this;
  }

  private function getHeadRefs() {
    if (!$this->headRefs) {
      $this->headRefs = array();
      $head_refs = $this->git->forEachRef(array(
        'refname',
        'refname:short',
        'upstream',
        'upstream:short',
        'upstream:track',
        'objectname',
        'objecttype',
        'tree',
        'parent',
        'HEAD',
        'subject',
        'body',
        'committerdate:raw',
      ), 'refs/heads');
      foreach ($head_refs as $ref) {
        $this->headRefs[] = ICFlowRef::newFromFields($ref);
      }
      $this->headRefs = mpull($this->headRefs, null, 'getName');
    }
    return $this->headRefs;
  }

  public function getFeature($feature_name) {
    return idx($this->getFeatures(), $feature_name);
  }

  private function getAllFeatures() {
    $features = array();
    foreach ($this->getHeadRefs() as $head) {
      $features[] = ICFlowFeature::newFromHead($head, $this->getGitAPI());
    }
    return mpull($features, null, 'getName');
  }

  private function getFeaturesBetween($root, $terminus = null) {
    if ($terminus == null) {
      return $this->getChildFeatures($root);
    }
    $features = array();
    $base = idx($this->getHeadRefs(), $terminus);
    if (!$base) {
      throw new UnexpectedValueException(
        pht('Invalid terminal branch specified: %s', $terminus));
    }
    do {
      $features[] = ICFlowFeature::newFromHead($base, $this->getGitAPI());
      if ($base->getName() === $root) {
        break;
      }
    } while ($base = idx($this->getHeadRefs(), $base->getUpstream()));

    return mpull($features, null, 'getName');
  }

  private function getChildFeatures($root) {
    $ref = idx($this->getHeadRefs(), $root);
    if (!$ref) {
      throw new UnexpectedValueException(
        pht('Invalid root branch specified: %s', $root));
    }
    $features = array();
    $current_level = array($root);
    $graph = $this->getTrackingGraph();
    while ($current_level) {
      $next_level = array();
      foreach ($current_level as $branch_name) {
        $ref = idx($this->getHeadRefs(), $branch_name);
        foreach ($graph->getDownstreams($branch_name) as $child_branch) {
          $next_level[] = $child_branch;
        }
        $features[] = ICFlowFeature::newFromHead($ref, $this->getGitAPI());
      }
      $current_level = $next_level;
    }

    return mpull($features, null, 'getName');
  }

  public function getFeatures() {
    if (!$this->features) {
      if ($this->getRootBranch() || $this->terminalBranch) {
        $this->features = $this->getFeaturesBetween($this->getRootBranch(),
          $this->terminalBranch);
      } else {
        $this->features = $this->getAllFeatures();
      }
    }
    return $this->features;
  }

  private function getRefNames() {
    return array_keys($this->getHeadRefs());
  }

  public function getFeatureNames() {
    return array_keys($this->getFeatures());
  }

  public function getTrackingGraph() {
    $tracking = array();
    foreach ($this->getHeadRefs() as $ref) {
      if (in_array($ref->getUpstream(), $this->getRefNames())) {
        $tracking[$ref->getName()] = $ref->getUpstream();
      }
    }
    $tracking = igroup($tracking, null);
    $graph = new ICGitBranchGraph();
    foreach ($tracking as $upstream => $downstreams) {
      $graph->addNodes(array($upstream => array_keys($downstreams)));
    }
    if ($this->getRootBranch() == $this->getGitAPI()->getDefaultRemoteBranch()) {
      return $graph->loadGraph();
    } else {
      // build truncated graph
      $edges = array();
      foreach ($graph->getNodes() as $upstream => $downstream) {
        $edges[$upstream] = $downstream;
      }
      $truncated_graph = new ICGitBranchGraph();
      $truncated_graph->addNodes(
        array($this->rootBranch => idx($edges, $this->rootBranch, array())));

      $to_visit = idx($edges, $this->rootBranch, array());
      while (!empty($to_visit)) {
        $branch = array_pop($to_visit);
        $to_visit = array_merge($to_visit, idx($edges, $branch, array()));
        $truncated_graph->addNodes(
          array($branch => idx($edges, $branch, array())));
      }
      return $truncated_graph->loadGraph();
    }
  }

  // fetches branches which have broken upstream
  public function getBrokenBranches() {
    $branches = array();
    foreach ($this->getHeadRefs() as $ref) {
      if ($ref->getUpstream() &&
          !$this->getGitAPI()->revParseVerify($ref->getUpstream())) {
        $branches[] = $ref->getName();
      }
    }
    return $branches;
  }

}
