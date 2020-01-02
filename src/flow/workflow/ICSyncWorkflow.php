<?php

final class ICSyncWorkflow extends ICArcanistWorkflow {

  public function getWorkflowName() {
    return 'sync';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **sync**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(
      "\n          Synchronizes revisions and revision metadata between your ".
      "local working copy and the\n".
      "          remote install. If no options are passed it will refresh ".
      "dependencies and revisions.\n          Synchronization is always started".
      ' from master branch');
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
      'force' => array(
        'help' => pht('Do not run any sanity checks.'),
      ),
    );
  }

  public function run() {
    $dependencies = $this->getArgument('dependencies', false);
    $revisions = $this->getArgument('revisions', false);
    if (!$dependencies && !$revisions) {
      $dependencies = true;
      $revisions = true;
    }
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
    }

    if ($revisions) {
      $this->runRevisionSync();
    }

    return 0;
  }

  protected function runRevisionSync() {
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
      $feature = $this->getFeature($branch_name);

      if (!$feature || !$feature->getAuthorPHID()) {
        // no revision or author
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
          $repository_api =
            ArcanistRepositoryAPI::newAPIFromConfigurationManager(
              $this->getConfigurationManager());
          $changes_planned =
            ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
              ArcanistDifferentialRevisionStatus::CHANGES_PLANNED);
          $diff_args = array(
            '--update',
            'D'.$revision_id,
            '--message',
            $message,
          );
          if ($branch_status[$branch_name] == $changes_planned) {
            array_push($diff_args, '--plan-changes');
          }
          $diff_workflow = $this->buildChildWorkflow('diff', $diff_args)
            ->setRepositoryAPI($repository_api);
          $diff_workflow->getArcanistConfiguration()
            ->willRunWorkflow('diff', $diff_workflow);
          $diff_workflow->run();
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
