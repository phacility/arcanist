<?php

/**
 * Lands a branch by rebasing, merging and amending it.
 *
 * @group workflow
 */
final class ArcanistLandWorkflow extends ArcanistBaseWorkflow {
  private $isGit;
  private $isGitSvn;
  private $isHg;
  private $isHgSvn;

  private $oldBranch;
  private $branch;
  private $onto;
  private $ontoRemoteBranch;
  private $remote;
  private $useSquash;
  private $keepBranch;
  private $shouldUpdateWithRebase;
  private $branchType;
  private $ontoType;
  private $preview;

  private $revision;
  private $messageFile;

  public function getRevisionDict() {
    return $this->revision;
  }

  public function getWorkflowName() {
    return 'land';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **land** [__options__] [__branch__] [--onto __master__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg

          Land an accepted change (currently sitting in local feature branch
          __branch__) onto __master__ and push it to the remote. Then, delete
          the feature branch. If you omit __branch__, the current branch will
          be used.

          In mutable repositories, this will perform a --squash merge (the
          entire branch will be represented by one commit on __master__). In
          immutable repositories (or when --merge is provided), it will perform
          a --no-ff merge (the branch will always be merged into __master__ with
          a merge commit).

          Under hg, bookmarks can be landed the same way as branches.
EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function getArguments() {
    return array(
      'onto' => array(
        'param' => 'master',
        'help' => pht('Land feature branch onto a branch other than the '.
                      'default (\'master\' in git, \'default\' in hg). You '.
                      'can change the default by setting '.
                      '\'arc.land.onto.default\' with `arc set-config` or '.
                      'for the entire project in .arcconfig.'),
      ),
      'hold' => array(
        'help' => pht('Prepare the change to be pushed, but do not actually '.
                      'push it.'),
      ),
      'keep-branch' => array(
        'help' => pht('Keep the feature branch after pushing changes to the '.
                      'remote (by default, it is deleted).'),
      ),
      'remote' => array(
        'param' => 'origin',
        'help' => pht('Push to a remote other than the default (\'origin\' '.
                      'in git).'),
      ),
      'merge' => array(
        'help' => pht('Perform a --no-ff merge, not a --squash merge. If the '.
                      'project is marked as having an immutable history, '.
                      'this is the default behavior.'),
        'supports' => array(
          'git',
        ),
        'nosupport'   => array(
          'hg' => pht('Use the --squash strategy when landing in mercurial.'),
        ),
      ),
      'squash' => array(
        'help' => pht('Perform a --squash merge, not a --no-ff merge. If the '.
                      'project is marked as having a mutable history, this '.
                      'is the default behavior.'),
        'conflicts' => array(
          'merge' => '--merge and --squash are conflicting merge strategies.',
        ),
      ),
      'delete-remote' => array(
        'help'      => pht('Delete the feature branch in the remote after '.
                           'landing it.'),
        'conflicts' => array(
          'keep-branch' => true,
        ),
      ),
      'update-with-rebase' => array(
        'help'    => pht('When updating the feature branch, use rebase '.
                         'instead of merge. This might make things work '.
                         'better in some cases. Set arc.land.update.default '.
                         'to \'rebase\' to make this the default.'),
        'conflicts' => array(
          'merge' => pht('The --merge strategy does not update the feature '.
                         'branch.'),
          'update-with-merge' => pht('Cannot be used with '.
                                     '--update-with-merge.'),
        ),
        'supports' => array(
          'git',
        ),
      ),
      'update-with-merge' => array(
        'help'    => pht('When updating the feature branch, use merge instead '.
                         'of rebase. This is the default behavior. Setting '.
                         'arc.land.update.default to \'merge\' can also be '.
                         'used to make this the default.'),
        'conflicts' => array(
          'merge' => pht('The --merge strategy does not update the feature '.
                         'branch.'),
          'update-with-rebase' => pht('Cannot be used with '.
                                      '--update-with-rebase.'),
        ),
        'supports' => array(
          'git',
        ),
      ),
      'revision' => array(
        'param' => 'id',
        'help'  => pht('Use the message from a specific revision, rather than '.
                       'inferring the revision based on branch content.'),
      ),
      'preview' => array(
        'help' => pht('Prints the commits that would be landed. Does not '.
                      'actually modify or land the commits.'),
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $this->readArguments();
    $this->validate();

    try {
      $this->pullFromRemote();
    } catch (Exception $ex) {
      $this->restoreBranch();
      throw $ex;
    }

    $this->printPendingCommits();
    if ($this->preview) {
      $this->restoreBranch();
      return 0;
    }

    $this->checkoutBranch();
    $this->findRevision();

    if ($this->useSquash) {
      $this->rebase();
      $this->squash();
    } else {
      $this->merge();
    }

    $this->push();

    if (!$this->keepBranch) {
      $this->cleanupBranch();
    }

    if ($this->oldBranch != $this->onto) {
      // If we were on some branch A and the user ran "arc land B",
      // switch back to A.
      if ($this->keepBranch || $this->oldBranch != $this->branch) {
        $this->restoreBranch();
      }
    }

    echo pht('Done.'), "\n";

    return 0;
  }

  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();
    $this->isGit = $repository_api instanceof ArcanistGitAPI;
    $this->isHg = $repository_api instanceof ArcanistMercurialAPI;

    if (!$this->isGit && !$this->isHg) {
      throw new ArcanistUsageException(
        pht("'arc land' only supports git and mercurial."));
    }

    if ($this->isGit) {
      $repository = $this->loadProjectRepository();
      $this->isGitSvn = (idx($repository, 'vcs') == 'svn');
    }

    if ($this->isHg) {
      $this->isHgSvn = $repository_api->isHgSubversionRepo();
    }

    $branch = $this->getArgument('branch');
    if (empty($branch)) {
      $branch = $this->getBranchOrBookmark();

      if ($branch) {
        $this->branchType = $this->getBranchType($branch);
        echo pht("Landing current %s '%s'.", $this->branchType, $branch), "\n";
        $branch = array($branch);
      }
    }

    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one branch or bookmark to land changes from.'));
    }
    $this->branch = head($branch);
    $this->keepBranch = $this->getArgument('keep-branch');

    $update_strategy = $this->getConfigFromAnySource(
      'arc.land.update.default',
      'merge');
    $this->shouldUpdateWithRebase = $update_strategy == 'rebase';
    if ($this->getArgument('update-with-rebase')) {
      $this->shouldUpdateWithRebase = true;
    } else if ($this->getArgument('update-with-merge')) {
      $this->shouldUpdateWithRebase = false;
    }
    $this->preview = $this->getArgument('preview');

    if (!$this->branchType) {
      $this->branchType = $this->getBranchType($this->branch);
    }

    $onto_default = $this->isGit ? 'master' : 'default';
    $onto_default = nonempty(
      $this->getConfigFromAnySource('arc.land.onto.default'),
      $onto_default);
    $this->onto = $this->getArgument('onto', $onto_default);
    $this->ontoType = $this->getBranchType($this->onto);

    $remote_default = $this->isGit ? 'origin' : '';
    $this->remote = $this->getArgument('remote', $remote_default);

    if ($this->getArgument('merge')) {
      $this->useSquash = false;
    } else if ($this->getArgument('squash')) {
      $this->useSquash = true;
    } else {
      $this->useSquash = false;
    }

    $this->ontoRemoteBranch = $this->onto;
    if ($this->isGitSvn) {
      $this->ontoRemoteBranch = 'trunk';
    } else if ($this->isGit) {
      $this->ontoRemoteBranch = $this->remote.'/'.$this->onto;
    }

    $this->oldBranch = $this->getBranchOrBookmark();
  }

  private function validate() {
    $repository_api = $this->getRepositoryAPI();

    if ($this->onto == $this->branch) {
      $message = pht(
        "You can not land a %s onto itself -- you are trying ".
        "to land '%s' onto '%s'. For more information on how to push ".
        "changes, see 'Pushing and Closing Revisions' in 'Arcanist User ".
        "Guide: arc diff' in the documentation.",
        $this->branchType,
        $this->branch,
        $this->onto);
      if (!$this->isHistoryImmutable()) {
        $message .= ' ' . pht("You may be able to 'arc amend' instead.");
      }
      throw new ArcanistUsageException($message);
    }

    if ($this->isHg) {
      if ($this->useSquash) {
        if (!$repository_api->supportsRebase()) {
          throw new ArcanistUsageException(
            pht("You must enable the rebase extension to use the --squash ".
                "strategy."));
        }
      }

      if ($this->branchType != $this->ontoType) {
        throw new ArcanistUsageException(pht(
          "Source %s is a %s but destination %s is a %s. When landing a ".
          "%s, the destination must also be a %s. Use --onto to specify a %s, ".
          "or set arc.land.onto.default in .arcconfig.",
          $this->branch,
          $this->branchType,
          $this->onto,
          $this->ontoType,
          $this->branchType,
          $this->branchType,
          $this->branchType));
      }
    }

    if ($this->isGit) {
      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(
          pht("Branch '%s' does not exist.", $this->branch));
      }
    }

    $this->requireCleanWorkingCopy();
  }

  private function checkoutBranch() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->getBranchOrBookmark() != $this->branch) {
      $repository_api->execxLocal(
        'checkout %s',
        $this->branch);
    }

    echo phutil_console_format(
      pht("Switched to %s **%s**. Identifying and merging...",
          $this->branchType,
          $this->branch).
      "\n");
  }

  private function printPendingCommits() {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistGitAPI) {
      list($out) = $repository_api->execxLocal(
        'log --oneline %s %s --',
        $this->branch,
        '^'.$this->onto);
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf('ancestor(%s,%s)',
          $this->onto,
          $this->branch));

      $branch_range = hgsprintf(
        'reverse((%s::%s) - %s)',
        $common_ancestor,
        $this->branch,
        $common_ancestor);

      list($out) = $repository_api->execxLocal(
        'log -r %s --template %s',
        $branch_range,
        '{node|short} {desc|firstline}\n');
    }

    if (!trim($out)) {
      $this->restoreBranch();
      throw new ArcanistUsageException(
          pht("No commits to land from %s.", $this->branch));
    }

    echo pht("The following commit(s) will be landed:\n\n%s", $out), "\n";
  }

  private function findRevision() {
    $repository_api = $this->getRepositoryAPI();

    $this->parseBaseCommitArgument(array($this->ontoRemoteBranch));

    $revision_id = $this->getArgument('revision');
    if ($revision_id) {
      $revision_id = $this->normalizeRevisionID($revision_id);
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if (!$revisions) {
        throw new ArcanistUsageException(pht(
          "No such revision '%s'!",
          "D{$revision_id}"));
      }
    } else {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array());
    }

    if (!count($revisions)) {
      throw new ArcanistUsageException(pht(
        "arc can not identify which revision exists on %s '%s'. Update the '.
        'revision with recent changes to synchronize the %s name and hashes, '.
        'or use 'arc amend' to amend the commit message at HEAD, or use ".
        "'--revision <id>' to select a revision explicitly.",
        $this->branchType,
        $this->branch,
        $this->branchType));
    } else if (count($revisions) > 1) {
      $message = pht(
        "There are multiple revisions on feature %s '%s' which are not ".
        "present on '%s':\n\n".
        "%s\n".
        "Separate these revisions onto different %s, or use --revision <id>' ".
        "to use the commit message from <id> and land them all.",
        $this->branchType,
        $this->branch,
        $this->onto,
        $this->renderRevisionList($revisions),
        $this->branchType.'s');
      throw new ArcanistUsageException($message);
    }

    $this->revision = head($revisions);

    $rev_status = $this->revision['status'];
    $rev_id = $this->revision['id'];
    $rev_title = $this->revision['title'];
    $rev_auxiliary = idx($this->revision, 'auxiliary', array());

    if ($this->revision['authorPHID'] != $this->getUserPHID()) {
      $other_author = $this->getConduit()->callMethodSynchronous(
        'user.query',
        array(
          'phids' => array($this->revision['authorPHID']),
        ));
      $other_author = ipull($other_author, 'userName', 'phid');
      $other_author = $other_author[$this->revision['authorPHID']];
      $ok = phutil_console_confirm(pht(
        "This %s has revision '%s' but you are not the author. Land this ".
        "revision by %s?",
        $this->branchType,
        "D{$rev_id}: {$rev_title}",
        $other_author));
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    if ($rev_status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $ok = phutil_console_confirm(pht(
        "Revision '%s' has not been accepted. Contine anyway?",
        "D{$rev_id}: {$rev_title}"));
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    if ($rev_auxiliary) {
      $phids = idx($rev_auxiliary, 'phabricator:depends-on', array());
      if ($phids) {
        $dep_on_revs = $this->getConduit()->callMethodSynchronous(
          'differential.query',
           array(
             'phids' => $phids,
             'status' => 'status-open',
           ));

        $open_dep_revs = array();
        foreach ($dep_on_revs as $dep_on_rev) {
          $dep_on_rev_id = $dep_on_rev['id'];
          $dep_on_rev_title = $dep_on_rev['title'];
          $dep_on_rev_status = $dep_on_rev['status'];
          $open_dep_revs[$dep_on_rev_id] = $dep_on_rev_title;
        }

        if (!empty($open_dep_revs)) {
          $open_revs = array();
          foreach ($open_dep_revs as $id => $title) {
            $open_revs[] = "    - D".$id.": ".$title;
          }
          $open_revs = implode("\n", $open_revs);

          echo pht("Revision '%s' depends on open revisions:\n\n%s",
                   "D{$rev_id}: {$rev_title}",
                   $open_revs);

          $ok = phutil_console_confirm(pht("Continue anyway?"));
          if (!$ok) {
            throw new ArcanistUserAbortException();
          }
        }
      }
    }

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $rev_id,
      ));

    $this->messageFile = new TempFile();
    Filesystem::writeFile($this->messageFile, $message);

    echo pht("Landing revision '%s'...",
             "D{$rev_id}: {$rev_title}"), "\n";

    $diff_phid = idx($this->revision, 'activeDiffPHID');
    if ($diff_phid) {
      $this->checkForBuildables($diff_phid);
    }
  }

  private function pullFromRemote() {
    $repository_api = $this->getRepositoryAPI();

    $local_ahead_of_remote = false;
    if ($this->isGit) {
      $repository_api->execxLocal('checkout %s', $this->onto);

      echo phutil_console_format(pht(
        "Switched to branch **%s**. Updating branch...\n",
        $this->onto));

      try {
        $repository_api->execxLocal('pull --ff-only --no-stat');
      } catch (CommandException $ex) {
        if (!$this->isGitSvn) {
          throw $ex;
        }
      }
      list($out) = $repository_api->execxLocal(
        'log %s..%s',
        $this->ontoRemoteBranch,
        $this->onto);
      if (strlen(trim($out))) {
        $local_ahead_of_remote = true;
      } else if ($this->isGitSvn) {
        $repository_api->execxLocal('svn rebase');
      }

    } else if ($this->isHg) {
      echo phutil_console_format(pht(
        "Updating **%s**...",
        $this->onto) . "\n");

      try {
        list($out, $err) = $repository_api->execxLocal('pull');

        $divergedbookmark = $this->onto.'@'.$repository_api->getBranchName();
        if (strpos($err, $divergedbookmark) !== false) {
          throw new ArcanistUsageException(phutil_console_format(pht(
            "Local bookmark **%s** has diverged from the server's **%s** ".
            "(now labeled **%s**). Please resolve this divergence and run ".
            "'arc land' again.",
            $this->onto,
            $this->onto,
            $divergedbookmark)));
        }
      } catch (CommandException $ex) {
        $err = $ex->getError();
        $stdout = $ex->getStdOut();

        // Copied from: PhabricatorRepositoryPullLocalDaemon.php
        // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the
        // behavior of "hg pull" to return 1 in case of a successful pull
        // with no changes. This behavior has been reverted, but users who
        // updated between Feb 1, 2012 and Mar 1, 2012 will have the
        // erroring version. Do a dumb test against stdout to check for this
        // possibility.
        // See: https://github.com/facebook/phabricator/issues/101/

        // NOTE: Mercurial has translated versions, which translate this error
        // string. In a translated version, the string will be something else,
        // like "aucun changement trouve". There didn't seem to be an easy way
        // to handle this (there are hard ways but this is not a common
        // problem and only creates log spam, not application failures).
        // Assume English.

        // TODO: Remove this once we're far enough in the future that
        // deployment of 2.1 is exceedingly rare?
        if ($err != 1 || !preg_match('/no changes found/', $stdout)) {
          throw $ex;
        }
      }

      // Pull succeeded.  Now make sure master is not on an outgoing change
      if ($repository_api->supportsPhases()) {
        list($out) = $repository_api->execxLocal(
          'log -r %s --template %s', $this->onto, "{phase}");
        if ($out != 'public') {
          $local_ahead_of_remote = true;
        }
      } else {
        // execManual instead of execx because outgoing returns
        // code 1 when there is nothing outgoing
        list($err, $out) = $repository_api->execManualLocal(
          'outgoing -r %s',
          $this->onto);

        // $err === 0 means something is outgoing
        if ($err === 0) {
          $local_ahead_of_remote = true;
        }
      }
    }

    if ($local_ahead_of_remote) {
      throw new ArcanistUsageException(pht(
        "Local %s '%s' is ahead of remote %s '%s', so landing a feature ".
        "%s would push additional changes. Push or reset the changes in '%s' ".
        "before running 'arc land'.",
        $this->ontoType,
        $this->onto,
        $this->ontoType,
        $this->ontoRemoteBranch,
        $this->ontoType,
        $this->onto));
    }
  }

  private function rebase() {
    $repository_api = $this->getRepositoryAPI();

    chdir($repository_api->getPath());
    if ($this->isGit) {
      if ($this->shouldUpdateWithRebase) {
        echo phutil_console_format(pht(
          "Rebasing **%s** onto **%s**",
          $this->branch,
          $this->onto)."\n");
        $err = phutil_passthru('git rebase %s', $this->onto);
        if ($err) {
          throw new ArcanistUsageException(pht(
            "'git rebase %s' failed. You can abort with 'git rebase ".
            "--abort', or resolve conflicts and use 'git rebase --continue' ".
            "to continue forward. After resolving the rebase, run 'arc land' ".
            "again.",
            $this->onto));
        }
      } else {
        echo phutil_console_format(pht(
          "Merging **%s** into **%s**",
          $this->branch,
          $this->onto)."\n");
        $err = phutil_passthru(
          'git merge --no-stat %s -m %s',
          $this->onto,
          pht("Automatic merge by 'arc land'"));
        if ($err) {
          throw new ArcanistUsageException(pht(
            "'git merge %s' failed. ".
            "To continue: resolve the conflicts, commit the changes, then run ".
            "'arc land' again. To abort: run 'git merge --abort'.",
            $this->onto));
        }
      }
    } else if ($this->isHg) {
      $onto_tip = $repository_api->getCanonicalRevisionName($this->onto);
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf("ancestor(%s, %s)",
          $this->onto,
          $this->branch));

      // Only rebase if the local branch is not at the tip of the onto branch.
      if ($onto_tip != $common_ancestor) {
        // keep branch here so later we can decide whether to remove it
        $err = $repository_api->execPassthru(
          'rebase -d %s --keepbranches',
          $this->onto);
        if ($err) {
          echo phutil_console_format("Aborting rebase\n");
          $repository_api->execManualLocal(
            'rebase --abort');
          $this->restoreBranch();
          throw new ArcanistUsageException(pht(
            "'hg rebase %s' failed and the rebase was aborted. ".
            "This is most likely due to conflicts. Manually rebase %s onto ".
            "%s, resolve the conflicts, then run 'arc land' again.",
            $this->onto,
            $this->branch,
            $this->onto));
        }
      }
    }

    $repository_api->reloadWorkingCopy();
  }

  private function squash() {
    $repository_api = $this->getRepositoryAPI();

    if ($this->isGit) {
      $repository_api->execxLocal('checkout %s', $this->onto);
      $repository_api->execxLocal(
        'merge --no-stat --squash --ff-only %s',
        $this->branch);
    } else if ($this->isHg) {
      // The hg code is a little more complex than git's because we
      // need to handle the case where the landing branch has child branches:
      // -a--------b  master
      //   \
      //    w--x  mybranch
      //        \--y  subbranch1
      //         \--z  subbranch2
      //
      // arc land --branch mybranch --onto master :
      // -a--b--wx  master
      //          \--y  subbranch1
      //           \--z  subbranch2

      $branch_rev_id = $repository_api->getCanonicalRevisionName($this->branch);

      // At this point $this->onto has been pulled from remote and
      // $this->branch has been rebased on top of onto(by the rebase()
      // function). So we're guaranteed to have onto as an ancestor of branch
      // when we use first((onto::branch)-onto) below.
      $branch_root = $repository_api->getCanonicalRevisionName(
        hgsprintf("first((%s::%s)-%s)",
          $this->onto,
          $this->branch,
          $this->onto));

      $branch_range = hgsprintf(
        "(%s::%s)",
        $branch_root,
        $this->branch);

      if (!$this->keepBranch) {
        $this->handleAlternateBranches($branch_root, $branch_range);
      }

      // Collapse just the landing branch onto master.
      // Leave its children on the original branch.
      $err = $repository_api->execPassthru(
        'rebase --collapse --keep --logfile %s -r %s -d %s',
        $this->messageFile,
        $branch_range,
        $this->onto);

      if ($err) {
        $repository_api->execManualLocal(
          'rebase --abort');
        $this->restoreBranch();
        throw new ArcanistUsageException(
          "Squashing the commits under {$this->branch} failed. ".
          "Manually squash your commits and run 'arc land' again.");
      }

      if ($repository_api->isBookmark($this->branch)) {
        // a bug in mercurial means bookmarks end up on the revision prior
        // to the collapse when using --collapse with --keep,
        // so we manually move them to the correct spots
        // see: http://bz.selenic.com/show_bug.cgi?id=3716
        $repository_api->execxLocal(
          'bookmark -f %s',
          $this->onto);

        $repository_api->execxLocal(
          'bookmark -f %s -r %s',
          $this->branch,
          $branch_rev_id);
      }

      // check if the branch had children
      list($output) = $repository_api->execxLocal(
        "log -r %s --template %s",
        hgsprintf("children(%s)", $this->branch),
        '{node}\n');

      $child_branch_roots = phutil_split_lines($output, false);
      $child_branch_roots = array_filter($child_branch_roots);
      if ($child_branch_roots) {
        // move the branch's children onto the collapsed commit
        foreach ($child_branch_roots as $child_root) {
          $repository_api->execxLocal(
            'rebase -d %s -s %s --keep --keepbranches',
            $this->onto,
            $child_root);
        }
      }

      // All the rebases may have moved us to another branch
      // so we move back.
      $repository_api->execxLocal('checkout %s', $this->onto);
    }
  }

  /**
   * Detect alternate branches and prompt the user for how to handle
   * them. An alternate branch is a branch that forks from the landing
   * branch prior to the landing branch tip.
   *
   * In a situation like this:
   *   -a--------b  master
   *     \
   *      w--x  landingbranch
   *       \  \-- g subbranch
   *        \--y  altbranch1
   *         \--z  altbranch2
   *
   * y and z are alternate branches and will get deleted by the squash,
   * so we need to detect them and ask the user what they want to do.
   *
   * @param string The revision id of the landing branch's root commit.
   * @param string The revset specifying all the commits in the landing branch.
   * @return void
   */
  private function handleAlternateBranches($branch_root, $branch_range) {
    $repository_api = $this->getRepositoryAPI();

    // Using the tree in the doccomment, the revset below resolves as follows:
    // 1. roots(descendants(w) - descendants(x) - (w::x))
    // 2. roots({x,g,y,z} - {g} - {w,x})
    // 3. roots({y,z})
    // 4. {y,z}
    $alt_branch_revset = hgsprintf(
      'roots(descendants(%s)-descendants(%s)-%R)',
      $branch_root,
      $this->branch,
      $branch_range);
    list($alt_branches) = $repository_api->execxLocal(
      "log --template %s -r %s",
      '{node}\n',
       $alt_branch_revset);

    $alt_branches = phutil_split_lines($alt_branches, false);
    $alt_branches = array_filter($alt_branches);

    $alt_count = count($alt_branches);
    if ($alt_count > 0) {
      $input = phutil_console_prompt(pht(
        "%s '%s' has %s %s(s) forking off of it that would be deleted ".
        "during a squash. Would you like to keep a non-squashed copy, rebase ".
        "them on top of '%s', or abort and deal with them yourself? ".
        "(k)eep, (r)ebase, (a)bort:",
        ucfirst($this->branchType),
        $this->branch,
        $alt_count,
        $this->branchType,
        $this->branch));

      if ($input == 'k' || $input == 'keep') {
        $this->keepBranch = true;
      } else if ($input == 'r' || $input == 'rebase') {
        foreach ($alt_branches as $alt_branch) {
          $repository_api->execxLocal(
            'rebase --keep --keepbranches -d %s -s %s',
            $this->branch,
            $alt_branch);
        }
      } else if ($input == 'a' || $input == 'abort') {
        $branch_string = implode("\n", $alt_branches);
        echo
          "\n",
          pht("Remove the %s starting at these revisions and ".
              "run arc land again:\n%s",
              $this->branchType.'s',
              $branch_string),
          "\n\n";
        throw new ArcanistUserAbortException();
      } else {
        throw new ArcanistUsageException(
          pht("Invalid choice. Aborting arc land."));
      }
    }
  }

  private function merge() {
    $rev_id = $this->revision['id'];
    $repository_api = $this->getRepositoryAPI();

    // In immutable histories, do a --no-ff merge to force a merge commit with
    // the right message.
    $repository_api->execxLocal('checkout %s', $this->onto);

    chdir($repository_api->getPath());
    if ($this->isGit) {
      $err = phutil_passthru(
        "git merge --log --no-ff --edit --no-commit -m 'Merging revision https://cr.goindex.com/D{$rev_id} from {$this->branch}' %s",
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(pht(
          "'git merge' failed. Your working copy has been left in a partially ".
          "merged state. You can: abort with 'git merge --abort'; or follow ".
          "the instructions to complete the merge."));
      }
    } else if ($this->isHg) {
      // HG arc land currently doesn't support --merge.
      // When merging a bookmark branch to a master branch that
      // hasn't changed since the fork, mercurial fails to merge.
      // Instead of only working in some cases, we just disable --merge
      // until there is a demand for it.
      // The user should never reach this line, since --merge is
      // forbidden at the command line argument level.
      throw new ArcanistUsageException(pht(
        "--merge is not currently supported for hg repos."));
    }
  }

  private function push() {
    $repository_api = $this->getRepositoryAPI();

    // these commands can fail legitimately (e.g. commit hooks)
    try {
      if ($this->isGit) {
        $repository_api->execxLocal(
          'commit -F %s',
          $this->messageFile);
      } else if ($this->isHg) {
        // hg rebase produces a commit earlier as part of rebase
        if (!$this->useSquash) {
          $repository_api->execxLocal(
            'commit --logfile %s',
            $this->messageFile);
        }
      }
      // We dispatch this event so we can run checks on the merged revision,
      // right before it gets pushed out. It's easier to do this in arc land
      // than to try to hook into git/hg.
      $this->dispatchEvent(
        ArcanistEventType::TYPE_LAND_WILLPUSHREVISION,
        array());
    } catch (Exception $ex) {
      $this->executeCleanupAfterFailedPush();
      throw $ex;
    }

    if ($this->getArgument('hold')) {
      echo phutil_console_format(pht(
        "Holding change in **%s**: it has NOT been pushed yet.",
        $this->onto). "\n");
    } else {
      echo pht('Pushing change...'), "\n\n";

      chdir($repository_api->getPath());

      if ($this->isGitSvn) {
        $err = phutil_passthru('git svn dcommit');
        $cmd = "git svn dcommit";
      } else if ($this->isGit) {
        $err = phutil_passthru(
          'git push %s %s',
          $this->remote,
          $this->onto);
        $cmd = "git push";
      } else if ($this->isHgSvn) {
        // hg-svn doesn't support 'push -r', so we do a normal push
        // which hg-svn modifies to only push the current branch and
        // ancestors.
        $err = $repository_api->execPassthru(
          'push %s',
          $this->remote);
        $cmd = "hg push";
      } else if ($this->isHg) {
        $err = $repository_api->execPassthru(
          'push -r %s %s',
          $this->onto,
          $this->remote);
        $cmd = "hg push";
      }

      if ($err) {
        $failed_str = pht('PUSH FAILED!');
        echo phutil_console_format("<bg:red>**   %s   **</bg>\n", $failed_str);
        $this->executeCleanupAfterFailedPush();
        if ($this->isGit) {
          throw new ArcanistUsageException(pht(
            "'%s' failed! Fix the error and run 'arc land' again.",
            $cmd));
        }
        throw new ArcanistUsageException(pht(
          "'%s' failed! Fix the error and push this change manually.",
          $cmd));
      }

      // If we know which repository we're in, try to tell Phabricator that we
      // pushed commits to it so it can update. This hint can help pull updates
      // more quickly, especially in rarely-used repositories.
      if ($this->getRepositoryCallsign()) {
        try {
          $this->getConduit()->callMethodSynchronous(
            'diffusion.looksoon',
            array(
              'callsign' => $this->getRepositoryCallsign(),
            ));
        } catch (ConduitClientException $ex) {
          // If we hit an exception, just ignore it. Likely, we are running
          // against a Phabricator which is too old to support this method.
          // Since this hint is purely advisory, it doesn't matter if it has
          // no effect.
        }
      }

      $mark_workflow = $this->buildChildWorkflow(
        'close-revision',
        array(
          '--finalize',
          '--quiet',
          $this->revision['id'],
        ));
      $mark_workflow->run();

      echo "\n";
    }
  }

  private function executeCleanupAfterFailedPush() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isGit) {
      $repository_api->execxLocal('reset --hard HEAD^');
      $this->restoreBranch();
    } else if ($this->isHg) {
      $repository_api->execxLocal(
        '--config extensions.mq= strip %s',
        $this->onto);
      $this->restoreBranch();
    }
  }

  private function cleanupBranch() {
    $repository_api = $this->getRepositoryAPI();

    echo pht('Cleaning up feature %s...', $this->branchType), "\n";
    if ($this->isGit) {
      list($ref) = $repository_api->execxLocal(
        'rev-parse --verify %s',
        $this->branch);
      $ref = trim($ref);
      $recovery_command = csprintf(
        'git checkout -b %s %s',
        $this->branch,
        $ref);
      echo pht('(Use `%s` if you want it back.)', $recovery_command), "\n";
      $repository_api->execxLocal(
        'branch -D %s',
        $this->branch);
    } else if ($this->isHg) {
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf("ancestor(%s,%s)",
          $this->onto,
          $this->branch));

      $branch_root = $repository_api->getCanonicalRevisionName(
        hgsprintf("first((%s::%s)-%s)",
          $common_ancestor,
          $this->branch,
          $common_ancestor));

      $repository_api->execxLocal(
        '--config extensions.mq= strip -r %s',
        $branch_root);

      if ($repository_api->isBookmark($this->branch)) {
        $repository_api->execxLocal(
          'bookmark -d %s',
          $this->branch);
      }
    }

    if ($this->getArgument('delete-remote')) {
      if ($this->isGit) {
        list($err, $ref) = $repository_api->execManualLocal(
          'rev-parse --verify %s/%s',
          $this->remote,
          $this->branch);

        if ($err) {
          echo pht('No remote feature %s to clean up.',
                   $this->branchType), "\n";
        } else {

          // NOTE: In Git, you delete a remote branch by pushing it with a
          // colon in front of its name:
          //
          //   git push <remote> :<branch>

          echo pht('Cleaning up remote feature %s...', $this->branchType), "\n";
          $repository_api->execxLocal(
            'push %s :%s',
            $this->remote,
            $this->branch);
        }
      } else if ($this->isHg) {
        // named branches were closed as part of the earlier commit
        // so only worry about bookmarks
        if ($repository_api->isBookmark($this->branch)) {
          $repository_api->execxLocal(
            'push -B %s %s',
            $this->branch,
            $this->remote);
        }
      }
    }
  }

  protected function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

  private function getBranchOrBookmark() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isGit) {
      $branch = $repository_api->getBranchName();
    } else if ($this->isHg) {
      $branch = $repository_api->getActiveBookmark();
      if (!$branch) {
        $branch = $repository_api->getBranchName();
      }
    }

    return $branch;
  }

  private function getBranchType($branch) {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isHg && $repository_api->isBookmark($branch)) {
      return "bookmark";
    }
    return "branch";
  }

  /**
   * Restore the original branch, e.g. after a successful land or a failed
   * pull.
   */
  private function restoreBranch() {
    $repository_api = $this->getRepositoryAPI();
    $repository_api->execxLocal(
      'checkout %s',
      $this->oldBranch);
    if ($this->isGit) {
      $repository_api->execxLocal(
        'submodule update --init --recursive');
    }
    echo phutil_console_format(
      "Switched back to {$this->branchType} **%s**.\n",
      $this->oldBranch);
  }


  /**
   * Check if a diff has a running or failed buildable, and prompt the user
   * before landing if it does.
   */
  private function checkForBuildables($diff_phid) {
    // NOTE: Since Harbormaster is still beta and this stuff all got added
    // recently, just bail if we can't find a buildable. This is just an
    // advisory check intended to prevent human error.

    try {
      $buildables = $this->getConduit()->callMethodSynchronous(
        'harbormaster.querybuildables',
        array(
          'buildablePHIDs' => array($diff_phid),
          'manualBuildables' => false,
        ));
    } catch (ConduitClientException $ex) {
      return;
    }

    if (!$buildables['data']) {
      // If there's no corresponding buildable, we're done.
      return;
    }

    $console = PhutilConsole::getConsole();

    $buildable = head($buildables['data']);

    if ($buildable['buildableStatus'] == 'passed') {
      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('BUILDS PASSED'),
        pht(
          'Harbormaster builds for the active diff completed successfully.'));
      return;
    }

    switch ($buildable['buildableStatus']) {
      case 'building':
        $message = pht(
          'Harbormaster is still building the active diff for this revision:');
        $prompt = pht('Land revision anyway, despite ongoing build?');
        break;
      case 'failed':
        $message = pht(
          'Harbormaster failed to build the active diff for this revision. '.
          'Build failures:');
        $prompt = pht('Land revision anyway, despite build failures?');
        break;
      default:
        // If we don't recognize the status, just bail.
        return;
    }

    $builds = $this->getConduit()->callMethodSynchronous(
      'harbormaster.querybuilds',
      array(
        'buildablePHIDs' => array($buildable['phid']),
      ));

    $console->writeOut($message."\n\n");
    foreach ($builds['data'] as $build) {
      switch ($build['buildStatus']) {
        case 'failed':
          $color = 'red';
          break;
        default:
          $color = 'yellow';
          break;
      }

      $console->writeOut(
        "    **<bg:".$color."> %s </bg>** %s: %s\n",
        phutil_utf8_strtoupper($build['buildStatusName']),
        pht('Build %d', $build['id']),
        $build['name']);
    }

    $console->writeOut(
      "\n%s\n\n    **%s**: __%s__",
      pht('You can review build details here:'),
      pht('Harbormaster URI'),
      $buildable['uri']);

    if (!$console->confirm($prompt)) {
      throw new ArcanistUserAbortException();
    }
  }

}
