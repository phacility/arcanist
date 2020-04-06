<?php

final class ICGraftWorkflow extends ICFlowBaseWorkflow {

  public function getWorkflowBaseName() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getArcanistWorkflowName() {
    return 'graft';
  }

  public function getFlowWorkflowName() {
    return 'graft';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **graft** __revision__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format("\n".
      "Grafts __revision__ and its dependencies (if any) onto your working ".
      "tree.\n");
  }

  public function getArguments() {
    return array(
      '*' => 'revision',
      'force' => array(
        'help' => pht('Do not run any sanity checks.'),
      ),
      'patch-landed' => array(
        'help' => pht('Try to patch landed/closed diffs.'),
      ),
    );
  }

  public function run() {
    $api = $this->getRepositoryAPI();
    $git_api = new ICGitAPI($api);
    $force = $this->getArgument('force', false);
    $patch_landed = $this->getArgument('patch-landed', false);
    $revision_name = trim((string)idx($this->getArgument('revision'), 0));
    $id_matches = array();
    preg_match('/^D(?P<id>[1-9]\d*)$/i', $revision_name, $id_matches);
    if (!$revision_id = (int)idx($id_matches, 'id')) {
      throw new ArcanistUsageException(
        'You must provide a revision (eg D123) as an argument.');
    }
    $revision = $this->integratorFlowEmulator(array($revision_id),
                                              array(),
                                              array(), true);
    $revision = head($revision);
    if (!$revision) {
      throw new ArcanistUsageException(pht('No revision "%s" found.',
                                           $revision_name));
    }

    if (!$graph = $this->buildDependencyGraph($revision_id)) {
      $this->graftRevisions(array($revision), $force);
      return 0;
    }
    $nodes = $graph->getNodes();
    foreach ($nodes as $phid => $depends_on_phids) {
      if (count($depends_on_phids) > 1) {
        throw new ArcanistUsageException(
          pht('A differential revision present in the parent dependency graph '.
              'for the chosen revision directly depends on multiple '.
              'revisions, indicating that it has been managed by a process '.
              'other than arc sync.'));
      }
    }
    $sorted_node_indexes = array_reverse($graph->getNodesInTopologicalOrder());
    $revisions = $this->integratorFlowEmulator(
      array(), $sorted_node_indexes, array(), true);
    $revisions = ipull($revisions, null, 'phid');
    $ordered_revisions = array();
    foreach ($sorted_node_indexes as $node_index) {
      $rid = idxv($revisions, array($node_index, 'id'));
      if ($git_api->doesRevisionExistInLog($rid) && !$patch_landed) {
            echo phutil_console_format(pht('**D%s** is already present in '.
                                           "your working copy, skipping...\n",
                                           $rid));
            continue;
          }
      $ordered_revisions[] = $revisions[$node_index];
    }
    $this->graftRevisions($ordered_revisions, $force);
    return 0;
  }

  protected function graftRevisions(array $revisions, $force = false) {
    $diffs = $this->loadDiffs($revisions);
    foreach ($revisions as $revision) {
      $id = $revision['id'];
      $diff = $diffs[$id];
      if (!$base_branch_name = idx($diff, 'branch')) {
        $base_branch_name = 'D'.$id;
      }
      $branch_name = $this->generateBranchName($base_branch_name);
      $this->checkoutBranch($branch_name, true);
      $patch_args = array(
        '--revision',
        $id,
        '--nobranch',
        '--skip-dependencies',
      );
      if ($force) {
        array_push($patch_args, '--force');
      }
      $patch_workflow = $this->buildChildWorkflow('patch', $patch_args);
      $patch_workflow->run();
    }
  }

  protected function loadDiffs(array $revisions) {
    $git = $this->getRepositoryAPI();
    $diffs = ipull($revisions, 'activeDiff', 'activeDiffPHID');
    $calls = array();
    foreach ($diffs as $diff_phid => $diff) {
      $diff_id = idx($diff, 'id');
      $scratch_filename = "$diff_phid.diff";
      $scratch_path = $git->getScratchFilePath($scratch_filename);
      $diffs[$diff_phid]['rawDiffScratchFilename'] = $scratch_filename;
      if (!Filesystem::pathExists($scratch_path)) {
        $calls[$diff_phid] = $this->getConduit()
          ->callMethod('differential.getrawdiff', array('diffID' => $diff_id));
      }
    }

    foreach (new FutureIterator($calls) as $diff_phid => $future) {
      $scratch_filename = $diffs[$diff_phid]['rawDiffScratchFilename'];
      $git->writeScratchFile($scratch_filename, $future->resolve());
    }

    return ipull($diffs, null, 'revisionID');
  }

}
