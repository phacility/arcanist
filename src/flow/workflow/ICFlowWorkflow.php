<?php

final class ICFlowWorkflow extends ICFlowBaseWorkflow {

  public function getWorkflowBaseName() {
    throw new PhutilMethodNotImplementedException();
  }

  public function getArcanistWorkflowName() {
    return 'flow';
  }

  public function getFlowWorkflowName() {
    return 'branch';
  }

  // we do not need network when we just branch out
  public function requiresAuthentication() {
    return false;
  }

  public function requiresConduit() {
    return true;
  }

  public function getCommandSynopses() {
    $workflow_name = $this->getWorkflowName();
    return phutil_console_format(<<<EOTEXT
      **{$workflow_name}** [__options__]
      **{$workflow_name}** __name__ [__options__]
      **{$workflow_name}** __name__ __upstream__ [__options__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT

          Without __name__, it lists the available branches and their revision
          status.

          With __name__, it creates or checks out a branch.  If the branch
          __name__ does not exist locally, it creates a branch with that
          name using your current branch as its upstream.  If the branch
          __name__ does exist, it checks out that branch.

          With __name__ and __upstream__, it creates a branch with an
          upstream determined by the user.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'json' => array(
        'help' => pht('Report results in JSON format.'),
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    $git_api = $this->getGitAPI();
    $names = $this->getArgument('branch');
    $existing_names = ipull($repository_api->getAllBranches(), 'name');

    if ($names) {
      $name = idx($names, 0);
      if (count($names) === 1 && in_array($name, $existing_names)) {
        $git_api->checkoutBranch($name);
      } else if (count($names) === 1 && !in_array($name, $existing_names)) {
        $git_api->createAndCheckoutBranchFromHead($name);
      } else if (count($names) === 2) {
        $upstream = idx($names, 1);
        $git_api->createAndCheckoutBranch($name, $upstream);
      } else {
        throw new ArcanistUsageException(phutil_console_format(pht(
          "Invalid branch arguments:\n".
          " - **No branches** to display your current tree\n".
          " - **One branch** to either switch to an existing branch, or ".
          "checkout a new branch from HEAD\n".
          " - **Two branches** to declare what branch the new branch will ".
          "track from, and checkout that new branch.\n")));
      }
      $this->markFlowUsage();
      return 0;
    }

    $this->authenticateConduit();

    if ($this->getArgument('json')) {
      $out = $this->getFlowData();
      echo phutil_json_encode($out);
      return 0;
    }

    $flow = $this->getFlow();

    $fields = $this->getFlowConfigurationManager()->getEnabledFields();
    $summary = (new ICFlowSummary())
      ->setWorkspace($flow)
      ->setFields($fields);

    $summary->draw();
    phutil_passthru('git status --short');

    return 0;
  }

  private function markFlowUsage() {
    $git = $this->getRepositoryAPI();
    $git->writeScratchFile('uses-arc-flow', 'true');
  }
}
