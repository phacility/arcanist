<?php

final class ICSyncWorkflow extends ICArcanistWorkflow {

  public function getWorkflowName() {
    return 'sync';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **sync** [rootbranch]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(
      "\n          Synchronizes revisions and revision metadata between your ".
      "local working copy and the\n".
      "          remote install. If no options are passed it will refresh ".
      "dependencies and revisions.\n          Synchronization by default is ".
      'started from default branch, you can specify different root branch');
  }

  public function getArguments() {
    return array(
      'dependencies' => array(
        'help' => pht(
          'Use upstream tracking of your local branches to set the '.
          'dependencies of their corresponding remote revisions.'),
      ),
      'revisions' => array(
        'help' => pht(
          "For any local branch that belongs to you and has a remote ".
          "revision, update the remote revision to reflect the state ".
          "of your local branch.\n\nFor any local branch that belongs ".
          "to another author, pull the latest remote revision from the ".
          "remote to the local branch."),
      ),
      'no-unit' => array(
        'help' => pht('Do not run unit tests.'),
      ),
      'no-lint' => array(
        'help' => pht('Do not run lint.'),
      ),
      'noautoland' => array(
        'help' => pht('Do not autoland this change (Skips interactive prompt)'),
      ),
      'plan-changes' => array(
        'help' => pht(
          'Create or update a revision without requesting a code review.'),
      ),
      'excuse' => array(
        'param' => 'excuse',
        'help' => pht(
          'Provide a prepared in advance excuse for any lints/tests '.
          'shall they fail.'),
      ),
      'force' => array(
        'help' => pht('Do not run any sanity checks.'),
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $default_branch = $this->getDefaultRemoteBranch();
    $dependencies = $this->getArgument('dependencies', false);
    $revisions = $this->getArgument('revisions', false);
    $branch = idx($this->getArgument('branch', false), 0, $default_branch);
    if (!$dependencies && !$revisions) {
      $dependencies = true;
      $revisions = true;
    }
    $this->setRootBranch($branch);
    $graph = $this->loadGitBranchGraph();
    $this->drawFlowTree();

    if ($dependencies && $this->consoleConfirm(tsprintf(
        'Each branch appearing in the graph above and meeting these two '.
        'criteria will have at least one dependency assigned to it on '.
        "differential.\n\n".
        '  - Branch has a differential revision associated with it.'.PHP_EOL.
        '  - Branch\'s parent also has a revision associated.'.
        "\n\nFurthermore, all existing dependencies of any updated revisions ".
        'will be removed. Please review the graph carefully.'.
        "\n\nProceed assigning dependencies?"))) {
      $parents = array();
      foreach ($graph->getNodesInTopologicalOrder() as $branch_name) {
        $feature = $this->getFeature($branch_name);
        if (!$feature || ($graph->getDepth($branch_name) < 2)) {
          continue;
        }

        $parent_branch = $graph->getUpstream($branch_name);
        if ($parent_feature = $this->getFeature($parent_branch)) {
          // do not add yourself as dependency
          if ($parent_feature->getRevisionPHID() !=
              $feature->getRevisionPHID()) {
            $parents[$feature->getRevisionPHID()][] =
              $parent_feature->getRevisionPHID();
          }
        }
      }

      $calls = array();
      foreach ($parents as $revision_phid => $depends_on) {
        $calls[] = $this->getConduit()->callMethod('differential.revision.edit',
          array(
            'objectIdentifier' => $revision_phid,
            'transactions' => array(
              array('type' => 'parents.set', 'value' => $depends_on),
            ),
        ));
      }

      foreach (new FutureIterator($calls) as $call) {
        $call->resolve();
      }
      $this->writeInfo("Dependencies updated", '');
    }

    if ($revisions) {
      $extra_diff_args = array();
      if ($this->getArgument('no-unit')) {
        $extra_diff_args[] = '--no-unit';
      }
      if ($this->getArgument('no-lint')) {
        $extra_diff_args[] = '--no-lint';
      }
      if ($this->getArgument('plan-changes')) {
        $extra_diff_args[] = '--plan-changes';
      }
      if ($this->getArgument('excuse')) {
        $extra_diff_args[] = '--excuse';
        $extra_diff_args[] = $this->getArgument('excuse');
      }
      if ($this->getArgument('noautoland')) {
        $extra_diff_args[] = '--noautoland';
      }

      $this->runRevisionSync($extra_diff_args);
    }

    return 0;
  }

  private function runDiffWorkflow($diff_args) {
    // instantiate new repository api for 'diff' child workflow.
    // otherwise, the base commit remains the same for all branches
    // (Branch A contains A, Branch B contains A+B, etc.) which is bad
    $repository_api =
      ArcanistRepositoryAPI::newAPIFromConfigurationManager(
        $this->getConfigurationManager());
    $diff_workflow = $this->buildChildWorkflow('diff', $diff_args)
     ->setRepositoryAPI($repository_api);
    $diff_workflow->getArcanistConfiguration()
      ->willRunWorkflow('diff', $diff_workflow);
    $diff_workflow->run();
  }

  protected function runRevisionSync($extra_diff_args = array()) {
    $git = $this->getRepositoryAPI();
    $graph = $this->loadGitBranchGraph();
    $initial_branch = $git->getBranchName();
    $flow_data = $this->getFlowData();
    $branch_staleness = ipull($flow_data, 'stale', 'name');
    $branch_status = ipull($flow_data, 'status', 'name');
    $authored_branches = array();
    $grafted_branches = array();
    $grafted_parent_branches = array();

    foreach ($graph->getNodesInTopologicalOrder() as $branch_name) {
      // generally default branch is not that interesting, skip it
      if ($branch_name == $this->getDefaultRemoteBranch()) {
        continue;
      }
      $feature = $this->getFeature($branch_name);

      // if feature/revision is missing ask if one should be created
      if (!$feature) {
        $confirm = $this->consoleConfirm(tsprintf(
          'Branch "%s" has no Differential Revision attached, do you want to '.
          'create one?', $branch_name));
        if (!$confirm) {
          $this->writeInfo(pht(
            'Skipping branch "%s"', $branch_name), '');
          continue;
        }
        // looks like branch has no Revision, create one and start over again
        echo "\n";
        $this->writeInfo(pht(
          'Creating new Differential Revision for branch "%s"',
          $branch_name), '');
        $this->checkoutBranch($branch_name, true);
        $this->runDiffWorkflow($extra_diff_args);
        $this->clearFlowWorkspace();
        $feature = $this->getFeature($branch_name);
        $this->writeInfo(pht(
          'Cascading changes after creation of new Differential Revision'), '');
        $this->buildChildWorkflow(
          'cascade',
          array($feature->getHead()->getUpstream()))->run();
        // refresh feature because some metadata might have changed after
        // cascade
        $this->clearFlowWorkspace();
        $feature = $this->getFeature($branch_name);
        $this->checkoutBranch($initial_branch);
        $this->writeInfo(pht(
          'You might have to rerun `arc sync` to synchronize dependencies'),
          '');
      }

      if (!$feature->getAuthorPHID()) {
        // no author, do not know how can revision have no author... keeping for
        // compatibility
        continue;
      }

      if ($feature->getAuthorPHID() === $this->getUserPHID()) {
        // the revision belongs to the current user. only update
        // it if the revision is open.
        if (!$this->isAnyClosedStatus($feature)) {
          $revision_id = $feature->getRevisionID();
          if (array_key_exists($revision_id, $authored_branches)) {
            echo "\n";
            $this->writeWarn('WARNING', phutil_console_format(pht(
              'Multiple branches exist pointing to **D%s**. '.
              'Please determine which branch you wish to sync '.
              'and manually run `arc diff` on that branch.', $revision_id)));
            unset($authored_branches[$revision_id]);
          } else {
            $authored_branches[$revision_id] = $branch_name;
          }

        }
        continue;
      }

      if (!$branch_staleness[$branch_name]) {
        // the local revision already matches the remote version.
        continue;
      }

      $grafted_branches[] = $branch_name;
      if ($graph->getDownstreams($branch_name)) {
        $grafted_parent_branches[] = $branch_name;
      }
    }

    if (count($grafted_branches)) {
      echo "\n".
        "The following branches belong to a different author and are out of ".
        "date with their \n remote revisions. Any local modifications that ".
        "have been made to these branches will \n be lost and they will match ".
        "the exact state of their respective remote revisions.\n\n";

      $this->renderBranchTable($grafted_branches);

      if (!$this->consoleConfirm('Proceed syncing local branches?')) {
        return 0;
      }

      $this->assertNoUncommittedChanges();
      $this->syncGraftedRevisions($grafted_branches);
    }

    echo "\n";
    $this->writeOkay('OKAY', 'All local branches belonging to other authors '.
                             'are up to date with their remote revisions.');
    echo "\n";

    if (count($grafted_parent_branches)) {
      $this->drawFlowTree();
      echo "\nThe following branches have children that need to be rebased:".
           "\n\n";
      $this->renderBranchTable($grafted_parent_branches);
      if ($this->consoleConfirm('Run cascade on these branches?', false)) {
        foreach ($grafted_parent_branches as $branch_name) {
          $this->writeInfo(pht('Cascade rebasing children of branch "%s"',
            $branch_name), '');
          $this->checkoutBranch($branch_name, true);
          $this->buildChildWorkflow('cascade', array())->run();
          echo "\n";
        }
      }
    }

    $this->drawFlowTree();

    if (count($authored_branches)) {
      echo "\nThe following branches are owned by you and have an open remote ".
           "revision:\n\n";
      $this->renderBranchTable($authored_branches);
      if ($this->consoleConfirm('Update each remote revision to match the '.
                                'current local branch state?')) {
        $prompt = pht('Enter an update message for your revisions:');
        if (!$message = phutil_console_prompt($prompt)) {
          throw new ArcanistUsageException('Update message is required.');
        }
        foreach ($authored_branches as $revision_id => $branch_name) {
          echo "\n";
          $this->writeInfo(pht('Diff\'ing branch "%s" to update D%d',
                               $branch_name, $revision_id), '');
          $this->checkoutBranch($branch_name, true);

          // instantiate new repository api for 'diff' child workflow.
          // otherwise, the base commit remains the same for all branches
          // (Branch A contains A, Branch B contains A+B, etc.) which is bad
          $changes_planned =
            ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
              ArcanistDifferentialRevisionStatus::CHANGES_PLANNED);
          $diff_args = array(
            '--update',
            'D'.$revision_id,
            '--message',
            $message,
          );
          $diff_args = array_merge($diff_args, $extra_diff_args);
          if ($branch_status[$branch_name] == $changes_planned) {
            array_push($diff_args, '--plan-changes');
          }
          $this->runDiffWorkflow($diff_args);
        }

        echo "\n";
        $this->writeOkay('OKAY', 'All remote revisions have been updated.');
        echo "\n";
      }
    }

    $this->checkoutBranch($initial_branch, true);
    return 0;
  }

  protected function renderBranchTable($branches) {
    $table = (new PhutilConsoleTable())
      ->setShowHeader(true)
      ->setBorders(true)
      ->addColumn('branch', array('title' => 'Branch'));
      foreach ($branches as $branch) {
      $table->addRow(array(
        'branch' => $branch,
      ));
    }
    $table->draw();
  }

  private function isAnyClosedStatus(ICFlowFeature $feature) {
    $closed_statuses = array_map(
      'ArcanistDifferentialRevisionStatus::getNameForRevisionStatus', array(
        ArcanistDifferentialRevisionStatus::CLOSED,
        ArcanistDifferentialRevisionStatus::ABANDONED,
      ));
    return in_array($feature->getRevisionStatusName(), $closed_statuses);
  }

}
