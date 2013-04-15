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
        'help' => "Land feature branch onto a branch other than the default ".
                  "('master' in git, 'default' in hg). You can change the ".
                  "default by setting 'arc.land.onto.default' with ".
                  "`arc set-config` or for the entire project in .arcconfig.",
      ),
      'hold' => array(
        'help' => "Prepare the change to be pushed, but do not actually ".
                  "push it.",
      ),
      'keep-branch' => array(
        'help' => "Keep the feature branch after pushing changes to the ".
                  "remote (by default, it is deleted).",
      ),
      'remote' => array(
        'param' => 'origin',
        'help' => "Push to a remote other than the default ('origin' in git).",
      ),
      'merge' => array(
        'help' => 'Perform a --no-ff merge, not a --squash merge. If the '.
                  'project is marked as having an immutable history, this is '.
                  'the default behavior.',
        'supports' => array(
          'git',
        ),
        'nosupport'   => array(
          'hg' => 'Use the --squash strategy when landing in mercurial.',
        ),
      ),
      'squash' => array(
        'help' => 'Perform a --squash merge, not a --no-ff merge. If the '.
                  'project is marked as having a mutable history, this is '.
                  'the default behavior.',
        'conflicts' => array(
          'merge' => '--merge and --squash are conflicting merge strategies.',
        ),
      ),
      'delete-remote' => array(
        'help'      => 'Delete the feature branch in the remote after '.
                       'landing it.',
        'conflicts' => array(
          'keep-branch' => true,
        ),
      ),
      'update-with-rebase' => array(
        'help'    => 'When updating the feature branch, use rebase instead of '.
                     'merge. This might make things work better in some cases.'.
                     'Set arc.land.update.default to \'rebase\' to make this '.
                     'default.',
        'conflicts' => array(
          'merge' => 'The --merge strategy does not update the feature branch.',
          'update-with-merge' => 'Cannot be used with --update-with-merge.',
        ),
        'supports' => array(
          'git',
        ),
      ),
      'update-with-merge' => array(
        'help'    => 'When updating the feature branch, use merge instead of '.
                     'rebase. This is the default behavior. '.
                     'Setting arc.land.update.default to \'merge\' can also '.
                     'be used to make this the default.',
        'conflicts' => array(
          'merge' => 'The --merge strategy does not update the feature branch.',
          'update-with-rebase' => 'Cannot be used with --update-with-rebase.',
        ),
        'supports' => array(
          'git',
        ),
      ),
      'revision' => array(
        'param' => 'id',
        'help'  => 'Use the message from a specific revision, rather than '.
                   'inferring the revision based on branch content.',
      ),
      'preview' => array(
        'help' => 'Prints the commits that would be landed. Does not actually '.
                  'modify or land the commits.'
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

    echo "Done.\n";

    return 0;
  }

  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();
    $this->isGit = $repository_api instanceof ArcanistGitAPI;
    $this->isHg = $repository_api instanceof ArcanistMercurialAPI;

    if (!$this->isGit && !$this->isHg) {
      throw new ArcanistUsageException(
        "'arc land' only supports git and mercurial.");
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
        echo "Landing current {$this->branchType} '{$branch}'.\n";
        $branch = array($branch);
      }
    }

    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        "Specify exactly one branch or bookmark to land changes from.");
    }
    $this->branch = head($branch);
    $this->keepBranch = $this->getArgument('keep-branch');

    $working_copy = $this->getWorkingCopy();
    $update_strategy = $working_copy->getConfigFromAnySource(
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
      $working_copy->getConfigFromAnySource('arc.land.onto.default'),
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
      $this->useSquash = !$this->isHistoryImmutable();
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
      $message =
        "You can not land a {$this->branchType} onto itself -- you are trying".
        "to land '{$this->branch}' onto '{$this->onto}'. For more".
        "information on how to push changes, see 'Pushing and Closing".
        "Revisions' in 'Arcanist User Guide: arc diff' in the documentation.";
      if (!$this->isHistoryImmutable()) {
        $message .= " You may be able to 'arc amend' instead.";
      }
      throw new ArcanistUsageException($message);
    }

    if ($this->isHg) {
      if ($this->useSquash) {
        if (!$repository_api->supportsRebase()) {
          throw new ArcanistUsageException(
            "You must enable the rebase extension to use ".
            "the --squash strategy.");
        }
      }

      if ($this->branchType != $this->ontoType) {
        throw new ArcanistUsageException(
          "Source {$this->branch} is a {$this->branchType} but destination ".
          "{$this->onto} is a {$this->ontoType}. When landing a ".
          "{$this->branchType}, the destination must also be a ".
          "{$this->branchType}. Use --onto to specify a {$this->branchType}, ".
          "or set arc.land.onto.default in .arcconfig.");
      }
    }

    if ($this->isGit) {
      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(
          "Branch '{$this->branch}' does not exist.");
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
      "Switched to {$this->branchType} **%s**. Identifying and merging...\n",
      $this->branch);
  }

  private function printPendingCommits() {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistGitAPI) {
      list($out) = $repository_api->execxLocal(
        'log --oneline %s %s',
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
          "No commits to land from {$this->branch}.");
    }

    echo "The following commit(s) will be landed:\n\n{$out}\n";
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
        throw new ArcanistUsageException("No such revision 'D{$revision_id}'!");
      }
    } else {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array(
          'authors' => array($this->getUserPHID()),
        ));
    }

    if (!count($revisions)) {
      throw new ArcanistUsageException(
        "arc can not identify which revision exists on {$this->branchType} ".
        "'{$this->branch}'. Update the revision with recent changes ".
        "to synchronize the {$this->branchType} name and hashes, or use ".
        "'arc amend' to amend the commit message at HEAD, or use ".
        "'--revision <id>' to select a revision explicitly.");
    } else if (count($revisions) > 1) {
      $message =
        "There are multiple revisions on feature {$this->branchType} ".
        "'{$this->branch}' which are not present on '{$this->onto}':\n\n".
        $this->renderRevisionList($revisions)."\n".
        "Separate these revisions onto different {$this->branchType}s, or use ".
        "'--revision <id>' to use the commit message from <id> and land them ".
        "all.";
      throw new ArcanistUsageException($message);
    }

    $this->revision = head($revisions);

    $rev_status = $this->revision['status'];
    $rev_id = $this->revision['id'];
    $rev_title = $this->revision['title'];
    $rev_auxiliary = idx($this->revision, 'auxiliary', array());

    if ($rev_status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $ok = phutil_console_confirm(
        "Revision 'D{$rev_id}: {$rev_title}' has not been ".
        "accepted. Continue anyway?");
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

          echo "Revision 'D{$rev_id}: {$rev_title}' depends ".
               "on open revisions:\n\n";
          echo $open_revs;

          $ok = phutil_console_confirm("Continue anyway?");
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

    echo "Landing revision 'D{$rev_id}: ".
         "{$rev_title}'...\n";
  }

  private function pullFromRemote() {
    $repository_api = $this->getRepositoryAPI();

    $local_ahead_of_remote = false;
    if ($this->isGit) {
      $repository_api->execxLocal('checkout %s', $this->onto);

      echo phutil_console_format(
        "Switched to branch **%s**. Updating branch...\n",
        $this->onto);

      try {
        $repository_api->execxLocal('pull --ff-only --no-stat');
      } catch (CommandException $ex) {
        if (!$this->isGitSvn) {
          throw $ex;
        }
        list($out) = $repository_api->execxLocal(
          'log %s..%s',
          $this->ontoRemoteBranch,
          $this->onto);
        if (strlen(trim($out))) {
          $local_ahead_of_remote = true;
        } else {
          $repository_api->execxLocal('svn rebase');
        }
      }

    } else if ($this->isHg) {
      echo phutil_console_format(
        "Updating **%s**...\n",
        $this->onto);

      try {
        list($out, $err) = $repository_api->execxLocal('pull');

        $divergedbookmark = $this->onto.'@'.$repository_api->getBranchName();
        if (strpos($err, $divergedbookmark) !== false) {
          throw new ArcanistUsageException(phutil_console_format(
            "Local bookmark **{$this->onto}** has diverged from the ".
            "server's **{$this->onto}** (now labeled ".
            "**{$divergedbookmark}**). Please resolve this divergence and ".
            "run 'arc land' again."));
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
          'log -r %s --template {phase}', $this->onto);
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
      throw new ArcanistUsageException(
        "Local {$this->ontoType} '{$this->onto}' is ahead of remote ".
        "{$this->ontoType} '{$this->ontoRemoteBranch}', so landing a feature ".
        "{$this->ontoType} would push additional changes. Push or reset the ".
        "changes in '{$this->onto}' before running 'arc land'.");
    }
  }

  private function rebase() {
    $repository_api = $this->getRepositoryAPI();

    echo phutil_console_format(
        "Rebasing **%s** onto **%s**\n",
        $this->branch,
        $this->onto);

    chdir($repository_api->getPath());
    if ($this->isGit) {
      if ($this->shouldUpdateWithRebase) {
        $err = phutil_passthru('git rebase %s', $this->onto);
        if ($err) {
          throw new ArcanistUsageException(
            "'git rebase {$this->onto}' failed. ".
            "You can abort with 'git rebase --abort', ".
            "or resolve conflicts and use 'git rebase ".
            "--continue' to continue forward. After resolving the rebase, ".
            "run 'arc land' again.");
        }
      } else {
        $err = phutil_passthru(
          'git merge --no-stat %s -m %s',
          $this->onto,
          "Automatic merge by 'arc land'");
        if ($err) {
          throw new ArcanistUsageException(
            "'git merge {$this->onto}' failed. ".
            "To continue: resolve the conflicts, commit the changes, then run ".
            "'arc land' again. To abort: run 'git merge --abort'.");
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
          throw new ArcanistUsageException(
            "'hg rebase {$this->onto}' failed and the rebase was aborted. ".
            "This is most likely due to conflicts. Manually rebase ".
            "{$this->branch} onto {$this->onto}, resolve the conflicts, ".
            "then run 'arc land' again.");
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
        "log -r %s --template '{node}\\n'",
        hgsprintf("children(%s)", $this->branch));

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
      "log --template '{node}\n' -r %s",
       $alt_branch_revset);

    $alt_branches = phutil_split_lines($alt_branches, false);
    $alt_branches = array_filter($alt_branches);

    $alt_count = count($alt_branches);
    if ($alt_count > 0) {
      $input = phutil_console_prompt(
        ucfirst($this->branchType)." '{$this->branch}' has {$alt_count} ".
        "{$this->branchType}(s) forking off of it that would be deleted ".
        "during a squash. Would you like to keep a non-squashed copy, rebase ".
        "them on top of '{$this->branch}', or abort and deal with them ".
        "yourself? (k)eep, (r)ebase, (a)bort:");

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
        echo "\nRemove the {$this->branchType}s starting at these revisions ".
          "and run arc land again:\n{$branch_string}\n\n";
        throw new ArcanistUserAbortException();
      } else {
        throw new ArcanistUsageException("Invalid choice. Aborting arc land.");
      }
    }
  }

  private function merge() {
    $repository_api = $this->getRepositoryAPI();

    // In immutable histories, do a --no-ff merge to force a merge commit with
    // the right message.
    $repository_api->execxLocal('checkout %s', $this->onto);

    chdir($repository_api->getPath());
    if ($this->isGit) {
      $err = phutil_passthru(
        'git merge --no-stat --no-ff --no-commit %s',
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(
          "'git merge' failed. Your working copy has been left in a partially ".
          "merged state. You can: abort with 'git merge --abort'; or follow ".
          "the instructions to complete the merge.");
      }
    } else if ($this->isHg) {
      // HG arc land currently doesn't support --merge.
      // When merging a bookmark branch to a master branch that
      // hasn't changed since the fork, mercurial fails to merge.
      // Instead of only working in some cases, we just disable --merge
      // until there is a demand for it.
      // The user should never reach this line, since --merge is
      // forbidden at the command line argument level.
      throw new ArcanistUsageException(
        "--merge is not currently supported for hg repos.");
    }
  }

  private function push() {
    $repository_api = $this->getRepositoryAPI();

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

    // We dispatch this event so we can run checks on the merged revision, right
    // before it gets pushed out. It's easier to do this in arc land than to
    // try to hook into git/hg.
    try {
      $this->dispatchEvent(
        ArcanistEventType::TYPE_LAND_WILLPUSHREVISION,
        array());
    } catch (Exception $ex) {
      $this->executeCleanupAfterFailedPush();
      throw $ex;
    }

    if ($this->getArgument('hold')) {
      echo phutil_console_format(
        "Holding change in **%s**: it has NOT been pushed yet.\n",
        $this->onto);
    } else {
      echo "Pushing change...\n\n";

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
        echo phutil_console_format("<bg:red>**   PUSH FAILED!   **</bg>\n");
        $this->executeCleanupAfterFailedPush();
        if ($this->isGit) {
          throw new ArcanistUsageException(
            "'{$cmd}' failed! Fix the error and run 'arc land' again.");
        }
        throw new ArcanistUsageException(
          "'{$cmd}' failed! Fix the error and push this change manually.");
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

    echo "Cleaning up feature {$this->branchType}...\n";
    if ($this->isGit) {
      list($ref) = $repository_api->execxLocal(
        'rev-parse --verify %s',
        $this->branch);
      $ref = trim($ref);
      $recovery_command = csprintf(
        'git checkout -b %s %s',
        $this->branch,
        $ref);
      echo "(Use `{$recovery_command}` if you want it back.)\n";
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
          echo "No remote feature {$this->branchType} to clean up.\n";
        } else {

          // NOTE: In Git, you delete a remote branch by pushing it with a
          // colon in front of its name:
          //
          //   git push <remote> :<branch>

          echo "Cleaning up remote feature branch...\n";
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
    echo phutil_console_format(
      "Switched back to {$this->branchType} **%s**.\n",
      $this->oldBranch);
  }

}
