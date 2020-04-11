<?php

class ArcanistFeatureWorkflow
  extends ArcanistArcWorkflow {

  private $branches;

  public function getWorkflowName() {
    return 'feature';
  }

  public function getWorkflowArguments() {
    return array(
      $this->newWorkflowArgument('view-all')
        ->setHelp(pht('Include closed and abandoned revisions.')),
      $this->newWorkflowArgument('by-status')
        ->setParameter('status')
        ->setHelp(pht('Sort branches by status instead of time.')),
      $this->newWorkflowArgument('output')
        ->setParameter('format')
        ->setHelp(
          pht(
            'With "json", show features in machine-readable JSON format.')),
      $this->newWorkflowArgument('branch')
        ->setWildcard(true),
    );
  }

  public function getWorkflowInformation() {
    return $this->newWorkflowInformation()
      ->setSynopsis(pht('Wrapper on "git branch" or "hg bookmark".'))
      ->addExample(pht('**feature** [__options__]'))
      ->addExample(pht('**feature** __name__ [__start__]'))
      ->setHelp(
        pht(<<<EOHELP
A wrapper on 'git branch' or 'hg bookmark'.

Without __name__, it lists the available branches and their revision status.

With __name__, it creates or checks out a branch. If the branch __name__
doesn't exist and is in format D123 then the branch of revision D123 is
checked out. Use __start__ to specify where the new branch will start. Use
'arc.feature.start.default' to set the default feature start location.
EOHELP
          ));
  }

  public function runWorkflow() {
    $repository_api = $this->getRepositoryAPI();
    if (!$repository_api) {
      throw new PhutilArgumentUsageException(
        pht(
          'This command must be run in a Git or Mercurial working copy.'));
    }

    $names = $this->getArgument('branch');
    if ($names) {
      if (count($names) > 2) {
        throw new ArcanistUsageException(pht('Specify only one branch.'));
      }
      return $this->checkoutBranch($names);
    }

    // TODO: Everything in this whole workflow that says "branch" means
    // "bookmark" in Mercurial.

    $branches = $repository_api->getAllBranchRefs();
    if (!$branches) {
      throw new ArcanistUsageException(
        pht('No branches in this working copy.'));
    }

    $states = array();
    foreach ($branches as $branch_key => $branch) {
      $state_ref = id(new ArcanistWorkingCopyStateRef())
        ->setCommitRef($branch->getCommitRef());

      $states[] = array(
        'branch' => $branch,
        'state' => $state_ref,
      );
    }

    $this->loadHardpoints(
      ipull($states, 'state'),
      array(
        ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS,
      ));


    $this->printBranches($states);

    return 0;
  }

  private function checkoutBranch(array $names) {
    $api = $this->getRepositoryAPI();

    if ($api instanceof ArcanistMercurialAPI) {
      $command = 'update %s';
    } else {
      $command = 'checkout %s';
    }

    $err = 1;

    $name = $names[0];
    if (isset($names[1])) {
      $start = $names[1];
    } else {
      $start = $this->getConfigFromAnySource('arc.feature.start.default');
    }

    $branches = $api->getAllBranches();
    if (in_array($name, ipull($branches, 'name'))) {
      list($err, $stdout, $stderr) = $api->execManualLocal($command, $name);
    }

    if ($err) {
      $match = null;
      if (preg_match('/^D(\d+)$/', $name, $match)) {
        $diff = $this->getConduitEngine()->resolveCall(
          'differential.querydiffs',
          array(
            'revisionIDs' => array($match[1]),
          ));
        $diff = head($diff);

        if ($diff['branch'] != '') {
          $name = $diff['branch'];
          list($err, $stdout, $stderr) = $api->execManualLocal(
            $command,
            $name);
        }
      }
    }

    if ($err) {
      if ($api instanceof ArcanistMercurialAPI) {
        $rev = '';
        if ($start) {
          $rev = csprintf('-r %s', $start);
        }

        $exec = $api->execManualLocal('bookmark %C %s', $rev, $name);

        if (!$exec[0] && $start) {
          $api->execxLocal('update %s', $name);
        }
      } else {
        $startarg = $start ? csprintf('%s', $start) : '';
        $exec = $api->execManualLocal(
          'checkout --track -b %s %C',
          $name,
          $startarg);
      }

      list($err, $stdout, $stderr) = $exec;
    }

    echo $stdout;
    fprintf(STDERR, '%s', $stderr);
    return $err;
  }

  private function printBranches(array $states) {
    static $color_map = array(
      'Closed'          => 'cyan',
      'Needs Review'    => 'magenta',
      'Needs Revision'  => 'red',
      'Accepted'        => 'green',
      'No Revision'     => 'blue',
      'Abandoned'       => 'default',
    );

    static $ssort_map = array(
      'Closed'          => 1,
      'No Revision'     => 2,
      'Needs Review'    => 3,
      'Needs Revision'  => 4,
      'Accepted'        => 5,
    );

    $out = array();
    foreach ($states as $objects) {
      $state = $objects['state'];
      $branch = $objects['branch'];

      $revision = null;
      if ($state->hasAmbiguousRevisionRefs()) {
        $status = pht('Ambiguous Revision');
      } else {
        $revision = $state->getRevisionRef();
        if ($revision) {
          $status = $revision->getStatusDisplayName();
        } else {
          $status = pht('No Revision');
        }
      }

      if (!$this->getArgument('view-all') && !$branch->getIsCurrentBranch()) {
        if ($status == 'Closed' || $status == 'Abandoned') {
          continue;
        }
      }

      $commit = $branch->getCommitRef();
      $epoch = $commit->getCommitEpoch();

      $color = idx($color_map, $status, 'default');
      $ssort = sprintf('%d%012d', idx($ssort_map, $status, 0), $epoch);

      if ($revision) {
        $desc = $revision->getFullName();
      } else {
        $desc = $commit->getSummary();
      }

      $out[] = array(
        'name'      => $branch->getBranchName(),
        'current'   => $branch->getIsCurrentBranch(),
        'status'    => $status,
        'desc'      => $desc,
        'revision'  => $revision ? $revision->getID() : null,
        'color'     => $color,
        'esort'     => $epoch,
        'epoch'     => $epoch,
        'ssort'     => $ssort,
      );
    }

    if (!$out) {
      // All of the revisions are closed or abandoned.
      return;
    }

    $len_name = max(array_map('strlen', ipull($out, 'name'))) + 2;
    $len_status = max(array_map('strlen', ipull($out, 'status'))) + 2;

    if ($this->getArgument('by-status')) {
      $out = isort($out, 'ssort');
    } else {
      $out = isort($out, 'esort');
    }
    if ($this->getArgument('output') == 'json') {
      foreach ($out as &$feature) {
        unset($feature['color'], $feature['ssort'], $feature['esort']);
      }
      echo json_encode(ipull($out, null, 'name'))."\n";
    } else {
      $table = id(new PhutilConsoleTable())
        ->setShowHeader(false)
        ->addColumn('current', array('title' => ''))
        ->addColumn('name',    array('title' => pht('Name')))
        ->addColumn('status',  array('title' => pht('Status')))
        ->addColumn('descr',   array('title' => pht('Description')));

      foreach ($out as $line) {
        $table->addRow(array(
          'current' => $line['current'] ? '*' : '',
          'name'    => tsprintf('**%s**', $line['name']),
          'status'  => tsprintf(
            "<fg:{$line['color']}>%s</fg>", $line['status']),
          'descr'   => $line['desc'],
        ));
      }

      $table->draw();
    }
  }

}
