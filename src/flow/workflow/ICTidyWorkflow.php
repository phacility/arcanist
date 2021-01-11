<?php

final class ICTidyWorkflow extends ICFlowBaseWorkflow {

  public function getWorkflowBaseName() {
    return 'tidy';
  }

  public function getArcanistWorkflowName() {
    return 'tidy';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **tidy**
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT

          Performs branch cleanup on your local working copy using revision
          metadata from the remote install.  It follows a series of steps
          in order:

            - recover
              Graft branches whose upstreams have been deleted onto default branch.
            - prune
              Delete branches whose corresponding differential revisions have
              been deleted, then graft any children of those branches onto the
              nearest remaining upstream parent.
            - garbage collection
              Optimizes local repository.

          Garbage collection comes in two forms:
            - auto
              Executes by default.  Faster but less thorough.  Adjust the
              `gc.auto` parameter in your git config to control threshold.
            - aggressive
              More aggressively optimize the repository at the expense of
              taking much more time.
              This is recommended every few hundred changesets.

            See https://git-scm.com/docs/git-gc for full documentation.

EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'aggressive' => array(
        'help' => pht('Perform thorough repository optimization.'),
      ),
      'skip-recover' => array(
        'help' => pht("Don't reattach dangling branches."),
      ),
      'skip-prune' => array(
        'help' => pht("Don't delete branches whose revisions are closed or ".
                      "abandoned."),
      ),
      'skip-prune-closed' => array(
        'help' => pht("Don't delete branches whose revisions are closed."),
      ),
      'prune-abandoned' => array(
        'help' => pht('Delete branches whose revisions are abandoned.'),
      ),
      'force' => array(
        'help' => pht('Do not run any sanity checks.'),
      ),
    );
  }

  public function run() {
    $skip_recover = $this->getArgument('skip-recover');
    $skip_prune = $this->getArgument('skip-prune');
    $skip_prune_closed = $this->getArgument('skip-prune-closed');
    $prune_abandoned = $this->getArgument('prune-abandoned');
    $aggressive = $this->getArgument('aggressive');

    $flow_data = $this->getFlowData();
    $this->drawFlowTree();
    $printed_branches = ipull($flow_data, null, 'name');
    $git = $this->getRepositoryAPI();
    $graph = $this->loadGitBranchGraph();

    $deleted = array();
    $closed = array();
    $abandoned = array();
    $orphaned = $this->loadBrokenBranches();
    foreach ($graph->getNodesInTopologicalOrder() as $branch_name) {
      if (!idx($printed_branches, $branch_name)) {
        continue;
      }
      if ($printed_branches[$branch_name]['status'] == 'Deleted') {
        $deleted[] = $branch_name;
      }
      if (!$graph->getDepth($branch_name)) {
        continue;
      }
      if ($printed_branches[$branch_name]['status'] == 'Closed') {
        $closed[] = $branch_name;
      }
      if ($printed_branches[$branch_name]['status'] == 'Abandoned') {
        $abandoned[] = $branch_name;
      }
    }

    $default_branch = $this->getDefaultRemoteBranch();

    if (!$skip_recover && $deleted && $this->consoleConfirm(tsprintf(
        'The branches listed as <fg:red>Deleted</fg> no longer exist and '.
        "their child branches are orphaned.\n\nAttach these orphaned branches ".
        "to '%s'?", $default_branch))) {
      foreach ($deleted as $deleted_branch) {
        $parent = $graph->getUpstream($deleted_branch);
        foreach ($graph->getDownstreams($deleted_branch) as $orphaned_branch) {
          $git->execxLocal(
            'branch --set-upstream-to=%s %s',
            $parent,
            $orphaned_branch);
        }
      }
    }

    if (!$skip_recover && $orphaned && $this->consoleConfirm(tsprintf(
        "The branches listed below:\n%s\nno longer have valid ".
        "upstream\n\n Attach these branches to '%s'?",
        implode("\n", $orphaned), $default_branch))) {
      foreach ($orphaned as $orphan) {
        $git->execxLocal(
          'branch --set-upstream-to=%s %s',
          $default_branch, $orphan);
      }
    }

    if (!$skip_prune && !$skip_prune_closed && $closed &&
        $this->consoleConfirm(tsprintf(
          'The branches listed as <fg:cyan>Closed</fg> correspond to '.
          'differential revisions which have been closed and can be '.
          'automatically deleted from your working copy.'.
          "\n\nDelete these branches?"))) {
      if (array_search($git->getBranchName(), $closed) !== false) {
        $git->execxLocal('checkout %s', $default_branch);
      }
      $git->execxLocal('branch -D %Ls', $closed);
      $this->recursivePruneBranch($closed, $graph, $git);
    }

    // You must explicitly ask for abandoned revisions to be removed since it's
    // not unlikely for someone to want to repurpose an abandoned revision.
    if ($prune_abandoned && $abandoned && $this->consoleConfirm(tsprintf(
        'The branches listed as Abandoned correspond to differential '.
        'revisions which have been abandoned and can be automatically deleted '.
        "from your working copy. \n\nDelete these branches?"))) {
      if (array_search($git->getBranchName(), $abandoned) !== false) {
        $git->execxLocal('checkout %', $default_branch);
      }
      $git->execxLocal('branch -D %Ls', $abandoned);
      $this->recursivePruneBranch($abandoned, $graph, $git);
    }

    // garbage collect
    $gc_flag = $aggressive ? '--aggressive' : '--auto';
    $git->execxLocal('gc '.$gc_flag);

    return 0;
  }

  /**
   * Remove the passed in branches setting any orphaned
   * branches to the proper parent branches.
   *
   * @param  Array $branches branches in working copy.
   * @param  ICGitBranchGraph $graph
   * @param  ArcanistGitAPI $git
   * @return void
   */
  private function recursivePruneBranch(
    array $branches,
    ICGitBranchGraph $graph,
    ArcanistGitAPI $git) {
    foreach ($branches as $prunable_branch) {
        $unpruned_parent = $prunable_branch;
        do {
          $unpruned_parent = $graph->getUpstream($unpruned_parent);
          $is_pruned = array_search($unpruned_parent, $branches) !== false;
        } while ($is_pruned || $graph->getDepth($unpruned_parent));
        foreach ($graph->getDownstreams($prunable_branch) as $orphaned_child) {
          if (array_search($orphaned_child, $branches) !== false) {
            continue;
          }
          $git->execxLocal(
            'branch --set-upstream-to=%s %s',
            $unpruned_parent,
            $orphaned_child);
        }
      }
  }

}
