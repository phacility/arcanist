<?php

/**
 * Base class that workflows within rIC should extend from.
 *
 * It mainly eases the process of collecting information about a users remote
 * and local data, like users revisions, diffs, branches and queue submissions.
 * It's very common to need to inspect both the local and remote state of
 * these things, but usually also relatively tedious.
 *
 * Methods use a naming convention to distinguish the reading of data which has
 * been cached for the duration of script execution vs loading live data.
 *
 *   - `getSomething`   if this has been called before during execution,
 *                      returns the cached result, otherwise it loads it
 *   - `loadSomething`  always loads the data, never cached, does not update
 *                      the cached result
 */
abstract class ICArcanistWorkflow extends ArcanistWorkflow {

  private $gitAPI = null;
  private $guard = null;
  private $flow = null;
  private $flowConfig = null;
  private $branches;
  private $revisions;
  private $diffs;
  private $rootBranch = null;
  // origin default branch
  private $defaultBranch = null;

  public function getSupportedRevisionControlSystems() {
    return array('git');
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  protected function searchMethodForID($method, $id) {
    $rval = $this->getConduit()->callMethodSynchronous($method, array(
      'constraints' => array(
        'ids' => array((int)$id),
      ),
    ));
    if (!idx($rval, 'data')) {
      throw new ArcanistUsageException(pht(
        'No results for id %s from %s.',
        $id,
        $method));
    }
    return head($rval['data']);
  }

  protected function getGitAPI() {
    if (!$this->gitAPI) {
      $this->gitAPI = new ICGitAPI($this->getRepositoryAPI());
    }
    return $this->gitAPI;
  }

  protected function loadGitBranchGraph() {
    return $this->getFlow()->getTrackingGraph();
  }

  protected function loadBrokenBranches() {
    return $this->getFlow()->getBrokenBranches();
  }

  protected function getFlow() {
    if (!$this->flow) {
      $this->flow = (new ICFlowWorkspace())
        ->setGitAPI($this->getGitAPI())
        ->setConduit($this->getConduit())
        ->setRootBranch($this->getRootBranch())
        ->setCache(ic_standard_cache(ic_blob_cache('flow'), 'workspace'));
    }
    return $this->flow;
  }

  protected function getFlowConfigurationManager() {
    if (!$this->flowConfig) {
      $this->flowConfig = (new ICFlowConfigurationManager())
        ->setArcanistConfigurationManager($this->getConfigurationManager());
    }
    return $this->flowConfig;
  }

  protected function getFeature($branch_name) {
    $feature = $this->getFlow()->getFeature($branch_name);
    if (!$feature || !$feature->getRevisionID()) {
      return false;
    }
    $this->getFlow()->loadRevisions();
    return $feature;
  }

  protected function integratorFlowEmulator($ids = array(), $phids = array(),
                                              $hashes = array(),
                                              $need_active_diff = false) {
    $futures = array();
    if ($ids) {
      $futures[] = $this->getConduit()
        ->callMethod('differential.query', array('ids' => $ids))->start();
    }

    if ($hashes) {
      $futures[] = $this->getConduit()
        ->callMethod('differential.query',
                     array('commitHashes' => $hashes))->start();
    }

   if ($phids) {
      $futures[] = $this->getConduit()
        ->callMethod('differential.query',
                     array('phids' => $phids))->start();
    }

    $results = array();
    foreach ($futures as $future) {
      foreach ($future->resolve() as $value) {
        $results[$value['id']] = $value;
      }
    }

    if ($need_active_diff) {
      $all_diffs = array();
      foreach ($results as $key => $value) {
        $all_diffs[] = $value['diffs'][0];
      }
      $diffs = $this->getConduit()
        ->callMethodSynchronous('differential.querydiffs',
                                array('ids' => $all_diffs));
      foreach ($results as $key => $value) {
        $results[$key]['activeDiff'] = $diffs[$value['diffs'][0]];
      }
    }

    return $results;
  }

  protected function checkoutBranch($name, $silent = false) {
    $api = $this->getRepositoryAPI();

    $command = 'checkout %s';

    $err = 1;

    $branches = $api->getAllBranches();
    if (in_array($name, ipull($branches, 'name'))) {
      list($err, $stdout, $stderr) = $api->execManualLocal($command, $name);
    }

    if ($err) {
      $exec = $api->execManualLocal(
        'checkout --track -b %s',
        $name);

      list($err, $stdout, $stderr) = $exec;
    }

    if (!$silent) {
      echo $stdout;
      if ($err) {
        throw new ArcanistUsageException(phutil_console_format(pht(
          "Cannot switch to another branch if changes would be overwritten.\n".
          "\t\tEither commit or stash changes in order to change to ".
          "**'{%s}'**.\n"), $name));
      }
    }

    return $err;
  }

  protected function getFlowData() {
    $flow = $this->getFlow();
    $fields = $this->getFlowConfigurationManager()->getEnabledFields();
    $summary = (new ICFlowSummary())
      ->setWorkspace($flow)
      ->setFields($fields);

    $values = $summary->getValues();
    $out = array();
    foreach ($values as $name => $data) {
      $fields = idx($data, 'fields');
      $monogram = idxv($fields, array('monogram', 'revision-id'));
      $ahead = (int)idxv($data, array('tracking', 'ahead'));
      $render_ahead = $ahead ? $ahead : '';
      $behind = (int)idxv($data, array('tracking', 'behind'));
      $render_behind = $behind ? $behind : '';
      $render_monogram = $monogram ? 'D'.$monogram : '';
      $out[] = array(
        'name'      => $name,
        'current'   => idxv($fields, array('current', 'current')),
        'status'    => ''.idxv($fields, array('status', 'status')),
        'desc'      => idxv($fields, array('description', 'description')),
        'color'     => idxv($fields, array('status', 'color')),
        'monogram'  => $render_monogram,
        'hash'      => idxv($fields, array('hash', 'hash')),
        'stale'     => idxv($fields, array('hash', 'stale')),
        'ahead'     => $render_ahead,
        'behind'    => $render_behind,
        'upstream'  => idxv($data, array('tracking', 'upstream')),
        'open-comments' => idxv($fields, array(
          'open-comments',
          'open-comments',
        )),
      );
    }

    return $out;
  }

  protected function drawFlowTree() {
    $flow = $this->getFlow();
    $fields = $this->getFlowConfigurationManager()->getEnabledFields();
    $summary = (new ICFlowSummary())
      ->setWorkspace($flow)
      ->setFields($fields);
    $summary->draw();
  }

  protected function buildDependencyGraph($revision_id) {
    $graph = null;
    if ($revision_id) {
      $revisions = $this->getConduit()
        ->callMethodSynchronous('differential.query',
                                array('ids' => array($revision_id)));
      if ($revisions) {
        $revision = head($revisions);
        $rev_auxiliary = idx($revision, 'auxiliary', array());
        $phids = idx($rev_auxiliary, 'phabricator:depends-on', array());
        if ($phids) {
          $revision_phid = $revision['phid'];
          $graph = (new ArcanistDifferentialDependencyGraph())
            ->setConduit($this->getConduit())
            ->setRepositoryAPI($this->getRepositoryAPI())
            ->setStartPHID($revision_phid)
            ->addNodes(array($revision_phid => $phids))
            ->loadGraph();
        }
      }
    }

    return $graph;
  }

  protected function syncGraftedRevisions(array $branch_names) {
    $git = $this->getRepositoryAPI();
    $graph = $this->loadGitBranchGraph();
    foreach ($branch_names as $branch_name) {
      $revision = $this->getRevisionForBranch($branch_name);
      $this->writeInfo(pht('Patching latest revision onto branch "%s"',
                           $branch_name), '');
      $this->checkoutBranch($branch_name, true);

      try {
        $base_revision = idxv($revision, array(
        'activeDiff',
                                               'sourceControlBaseRevision',
        ));
        $git->execxLocal('reset --hard %s', $base_revision);
      } catch (CommandException $e) {
        // the source control base revision doesn't exist in this working copy.
        // this typically occurs when this branch is a child dependency of
        // another grafted branch, so reset to the parent.
        if ($upstream = $graph->getUpstream($branch_name)) {
          $git->execxLocal('reset --hard %s', $upstream);
        } else {
          throw new Exception(pht('Cannot determine base revision for branch '.
                                  '"%s".', $branch_name));
        }
      }

      $patch_workflow = $this->buildChildWorkflow('patch', array(
        '--revision',
        $revision['id'],
        '--nobranch',
        '--skip-dependencies',
        '--force',
      ));
      $patch_workflow->run();
    }
  }

  protected function generateBranchName($base) {
    $branches = $this->getRepositoryAPI()->getAllBranches();
    $branch_name = $base;
    $index = 0;
    while (isset($branches[$branch_name])) {
      $branch_name = $base.'_'.++$index;
    }
    return $branch_name;
  }

  /**
   * Wrapper for phutil_console_confirm that respects the 'force' flag.
   */
  protected function consoleConfirm($prompt, $default_no = true) {
    $force = $this->getArgument('force', false);
    return $force || phutil_console_confirm($prompt, $default_no);
  }

  protected function assertNoUncommittedChanges() {
    $git = $this->getRepositoryAPI();
    if ($git->getUncommittedChanges()) {
      throw new ArcanistUsageException(
        pht(
          'You have uncommitted changes in this branch. Stage and commit, '.
          'stash, or revert them before proceeding.'));
    }
  }

  public function setRootBranch($root_branch) {
    if ($this->getRootBranch() != $root_branch) {
      // changing rootBranch changes flow workspace
      $this->flow = null;
    }
    $this->rootBranch = $root_branch;
    return $this;
  }

  public function getRootBranch() {
    if ($this->rootBranch === null) {
      $this->rootBranch = $this->getDefaultRemoteBranch();
    }
    return $this->rootBranch;
  }

  public function getDefaultRemoteBranch() {
    if ($this->defaultBranch === null) {
      $this->defaultBranch = $this->getGitAPI()->getDefaultRemoteBranch();
    }
    return $this->defaultBranch;
  }

  public function clearFlowWorkspace() {
    $this->flow = null;
  }
}
