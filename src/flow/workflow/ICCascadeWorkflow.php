<?php

final class ICCascadeWorkflow extends ICFlowBaseWorkflow {

  public function getWorkflowBaseName() {
    return 'cascade';
  }

  public function getArcanistWorkflowName() {
    return 'cascade';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **cascade** [--halt-on-conflict] [rootbranch]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT

          Automates the process of rebasing and patching local working branches
          and their associated differential diffs. Cascades from current branch
          if branch is not specified.

EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'halt-on-conflict' => array(
        'help' => "Rather than aborting any rebase attempts, cascade will drop".
                  " the user\ninto the conflicted branch in a rebase state.",
        'short' => 'hc',
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $this->cascade();
    return 0;
  }

  private function cascade() {
    $graph = $this->loadGitBranchGraph();
    $api = $this->getRepositoryAPI();
    if ($this->isInRebase($api)) {
      throw new ArcanistUsageException(
        phutil_console_format(" You are in a rebase process currently.\n".
                              " Get more information about this by running:".
                              "\n\n".
                              "      git status\n\n".
                              " To abort the current rebase, run:\n\n".
                              "      git rebase --abort\n\n".
                              " Aborting cascade.\n"));
    }
    $branch_name = idx($this->getArgument('branch'), 0, null);
    if (!$branch_name) {
       $branch_name = $api->getBranchName();
       if (!$branch_name) {
           throw new ArcanistUsageException('Unable to find a branch, are you '.
             'in `Detached HEAD` state?');
       }
       echo "Cascading children of current branch.\n";
    } else {
       echo phutil_console_format('Cascading children of <fg:green>%s</fg> '.
                                  "branch.\n", $branch_name);
    }
    echo ICConsoleTree::drawTreeColumn($branch_name, 0, false, '').PHP_EOL;
    if (!$this->rebaseChildren($graph, $branch_name)) {
      $this->writeWarn("WARNING", phutil_console_format('Some of cascading rebases failed, '.
                                 'you can run <fg:red>arc cascade '.
                                 '--halt-on-conflict</fg> which will halt at '.
                                 'failure point'.PHP_EOL));
    }

    $this->checkoutBranch($branch_name);
  }

  private function isInRebase($api) {
    list($err, $stdout) = $api->execManualLocal('status');
    $in_rebase = strpos($stdout, 'rebase in progress;') !== false;
    return $in_rebase;
  }

  private function rebaseForkPoint($branch_name, $child_branch) {
    return $this->getRepositoryAPI()->execManualLocal(
      'rebase --fork-point %s %s',
      $branch_name,
      $child_branch);
  }

  private function rebaseOnto($branch_name, $base, $child_branch) {
    return $this->getRepositoryAPI()->execManualLocal(
      'rebase --onto %s %s %s',
      $branch_name,
      $base,
      $child_branch);
  }

  private function rebaseChildren(ICGitBranchGraph $graph, $branch_name) {
    $api = $this->getRepositoryAPI();
    $downstreams = $graph->getDownstreams($branch_name);
    $had_conflict = false;
    foreach ($downstreams as $index => $child_branch) {
      echo ICConsoleTree::drawTreeColumn(
        $child_branch,
        $graph->getDepth($child_branch),
        false,
        '');
      list($err, $stdout, $stderr) = $this->rebaseForkPoint($branch_name,
                                                            $child_branch);
      if ($err) {
        // feature contains hash of point where differential revision
        // started, we can use it to rebase out changes
        $feature = $this->getFeature($child_branch);
        if ($feature) {
          echo ' usual `git rebase` flow failed, trying different '.
               "technique (rebase --onto)";
          $api->execxLocal('rebase --abort');
          list($err, $stdout, $stderr) = $this->rebaseOnto($branch_name,
                            $feature->getRevisionFirstCommit().'^',
                            $child_branch);
        }
      }

      if ($err) {
        echo phutil_console_format(" <fg:red>%s</fg>\n", 'FAIL');
        if ($this->getArgument('halt-on-conflict') || $this->userHaltConfig()) {
          $conflict = $this->extractConflictFromRebase($stdout);
          throw new ArcanistUsageException(
            phutil_console_format(" <fg:red>%s</fg>\n".
              " Navigate to that file to correct the conflict, then run:\n\n".
              "        git add <file(s)>\n".
              "        git rebase --continue\n\n".
              " Then continue on with cascading. To abort this process, run:".
              "\n\n".
              "        git rebase --abort\n\n".
              " You are now in branch '**%s**'.\n", $conflict, $child_branch));
        } else {
          $api->execxLocal('rebase --abort');
          $had_conflict = true;
          continue;
        }
      } else {
        echo phutil_console_format(" <fg:green>%s</fg>\n", 'OK');
      }

      if (!$this->rebaseChildren($graph, $child_branch)) {
        $had_conflict = true;
      }
    }
    return !$had_conflict;
  }

  private function userHaltConfig() {
    $should_halt = $this->getConfigFromAnySource('cascade.halt');
    return $should_halt;
  }

  private function extractConflictFromRebase($stdout) {
    // Find conflict, only take the line it is enumerated on using
    $result = null;
    preg_match("/CONFLICT(.*)\n/sU", $stdout, $result);
    return rtrim(head($result));
  }
}
