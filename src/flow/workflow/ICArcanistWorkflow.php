<?php

/**
 * Base class that workflows within rIC should extend from.
 *
 * It mainly eases the process of collecting information about a users remote and local data, like
 * users revisions, diffs, branches and queue submissions.  It's very common to need to inspect
 * both the local and remote state of these things, but usually also relatively tedious.
 *
 * Methods use a naming convention to distinguish the reading of data which has been cached for
 * the duration of script execution vs loading live data.
 *
 *   - `getSomething`   if this has been called before during execution, returns the cached result,
 *                      otherwise it loads it
 *   - `loadSomething`  always loads the data, never cached, does not update the cached result
 */
abstract class ICArcanistWorkflow extends ArcanistWorkflow {

  private $gitAPI = null;
  private $guard = null;
  private $flow = null;
  private $flowConfig = null;
  private $branches;
  private $revisions;
  private $diffs;

  public function getSupportedRevisionControlSystems() {
    return ['git'];
  }

  public function requiresAuthentication() {
    if ($this->isOffline()) {
      $this->writeWarn(
        pht('OFFLINE'),
        pht('No api calls can be made to Phabricator.'));
      return false;
    }
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function isOffline() {
    return (bool)$this->getConfigurationManager()
      ->getConfigFromAnySource('offline');
  }

  protected function searchMethodForID($method, $id) {
    $rval = $this->getConduit()->callMethodSynchronous($method, [
      'constraints' => [
        'ids' => [(int)$id],
      ],
    ]);
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

  protected function beginDangerousWorkspaceModifications() {
    if ($this->guard !== null) {
      throw new Exception('Already performing dangerous workspace modifications!');
    }
    $this->guard = new ICGitWorkspaceGuard($this->getGitAPI());
  }

  protected function endDangerousWorkspaceModifications() {
    $this->guard->unguard();
    $this->guard = null;
  }

  protected function getFlow() {
    if (!$this->flow) {
      $this->flow = (new ICFlowWorkspace())
        ->setGitAPI($this->getGitAPI())
        ->setConduit($this->getConduit())
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

  protected function getBranches() {
    if (!$this->branches) {
      $repository_api = $this->getRepositoryAPI();
      $branches = $repository_api->getAllBranches();
      if (!$branches) {
        throw new ArcanistUsageException(
          pht('No branches in this working copy.'));
      }

      $this->branches = $this->loadCommitInfo($branches);
    }
    return $this->branches;
  }

  protected function loadCommitInfo(array $branches) {
    $repository_api = $this->getRepositoryAPI();

    $futures = [];
    foreach ($branches as $branch) {
      // NOTE: "-s" is an option deep in git's diff argument parser that
      // doesn't seem to have much documentation and has no long form. It
      // suppresses any diff output.
      $futures[$branch['name']] = $repository_api->execFutureLocal(
        'show -s --format=%C %s --',
        '%H%x01%ct%x01%T%x01%s%x01%s%n%n%b',
        $branch['name']);
    }

    $branches = ipull($branches, null, 'name');

    $futures = id(new FutureIterator($futures))
      ->limit(16);
    foreach ($futures as $name => $future) {
      list($info) = $future->resolvex();
      list($hash, $epoch, $tree, $desc, $text) = explode("\1", trim($info), 5);

      $branch = $branches[$name] + [
        'hash' => $hash,
        'desc' => $desc,
        'tree' => $tree,
        'epoch' => (int)$epoch,
      ];

      try {
        $message = ArcanistDifferentialCommitMessage::newFromRawCorpus($text);
        $id = $message->getRevisionID();

        $branch['revisionID'] = $id;
      } catch (ArcanistUsageException $ex) {
        // In case of invalid commit message which fails the parsing,
        // do nothing.
        $branch['revisionID'] = null;
      }

      $branches[$name] = $branch;
    }

    return $branches;
  }

  protected function getRevisionForBranch($branch_name) {
    $branch = idx($this->getBranches(), $branch_name, []);
    if (!$revision_id = idx($branch, 'revisionID')) {
      return false;
    }
    return idx($this->getRevisions(), $revision_id);
  }

  protected function getRevisions() {
    if (!$this->revisions) {
      $this->revisions = $this->loadRevisions($this->getBranches());
    }
    return $this->revisions;
  }

  protected function loadRevisions(array $branches) {
    if ($this->isOffline()) {
      return [];
    }
    $ids = [];
    $hashes = [];

    foreach ($branches as $branch) {
      if ($branch['revisionID']) {
        $ids[] = $branch['revisionID'];
      }
      $hashes[] = ['gtcm', $branch['hash']];
      $hashes[] = ['gttr', $branch['tree']];
    }

    $params = [];

    if ($ids) {
      $params['ids'] = $ids;
    }

    if ($hashes) {
      $params['commitHashes'] = $hashes;
    }

    $results = $this->getConduit()->callMethodSynchronous('integrator.flow', $params);
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
          "\t\t Either commit or stash changes in order to change to **'{$name}'**.\n")));
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
    $out = [];
    foreach ($values as $name => $data) {
      $fields = idx($data, 'fields');
      $monogram = idxv($fields, ['monogram', 'revision-id']);
      $ahead = (int)idxv($data, ['tracking', 'ahead']);
      $render_ahead = $ahead ?: '';
      $behind = (int)idxv($data, ['tracking', 'behind']);
      $render_behind = $behind ?: '';
      $render_monogram = $monogram ? 'D'.$monogram : '';
      $out[] = [
        'name'      => $name,
        'current'   => idxv($fields, ['current', 'current']),
        'status'    => ''.idxv($fields, ['status', 'status']),
        'desc'      => idxv($fields, ['description', 'description']),
        'color'     => idxv($fields, ['status', 'color']),
        'monogram'  => $render_monogram,
        'hash'      => idxv($fields, ['hash', 'hash']),
        'stale'     => idxv($fields, ['hash', 'stale']),
        'ahead'     => $render_ahead,
        'behind'    => $render_behind,
        'upstream'  => idxv($data, ['tracking', 'upstream']),
      ];
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
    if (!$this->isOffline() && $revision_id) {
      $revisions = $this->getConduit()->callMethodSynchronous('differential.query', [
        'ids' => [$revision_id],
      ]);
      if ($revisions) {
        $revision = head($revisions);
        $rev_auxiliary = idx($revision, 'auxiliary', []);
        $phids = idx($rev_auxiliary, 'phabricator:depends-on', []);
        if ($phids) {
          $revision_phid = $revision['phid'];
          $graph = (new ArcanistDifferentialDependencyGraph())
            ->setConduit($this->getConduit())
            ->setRepositoryAPI($this->getRepositoryAPI())
            ->setStartPHID($revision_phid)
            ->addNodes([$revision_phid => $phids])
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
      $this->writeInfo(pht('Patching latest revision onto branch "%s"', $branch_name), '');
      $this->checkoutBranch($branch_name, true);

      try {
        $base_revision = idxv($revision, ['activeDiff', 'sourceControlBaseRevision']);
        $git->execxLocal('reset --hard %s', $base_revision);
      } catch(CommandException $e) {
        // the source control base revision doesn't exist in this working copy. this
        // typically occurs when this branch is a child dependency of another grafted
        // branch, so reset to the parent.
        if ($upstream = $graph->getUpstream($branch_name)) {
          $git->execxLocal('reset --hard %s', $upstream);
        } else {
          throw new Exception(pht('Cannot determine base revision for branch "%s".', $branch_name));
        }
      }

      $patch_workflow = $this->buildChildWorkflow('patch', [
        '--revision',
        $revision['id'],
        '--nobranch',
        '--skip-dependencies',
        '--force',
      ]);
      $patch_workflow->run();
    }
  }

  protected function generateBranchName($base) {
    $branches = $this->getBranches();
    $branch_name = $base;
    $index = 0;
    while (idx($branches, $branch_name)) {
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

}
