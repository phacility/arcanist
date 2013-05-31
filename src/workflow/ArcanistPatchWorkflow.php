<?php

/**
 * Applies changes from Differential or a file to the working copy.
 *
 * @group workflow
 */
final class ArcanistPatchWorkflow extends ArcanistBaseWorkflow {

  const SOURCE_BUNDLE         = 'bundle';
  const SOURCE_PATCH          = 'patch';
  const SOURCE_REVISION       = 'revision';
  const SOURCE_DIFF           = 'diff';

  private $source;
  private $sourceParam;

  public function getWorkflowName() {
    return 'patch';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **patch** __D12345__
      **patch** __--revision__ __revision_id__
      **patch** __--diff__ __diff_id__
      **patch** __--patch__ __file__
      **patch** __--arcbundle__ __bundlefile__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, svn, hg
          Apply the changes in a Differential revision, patchfile, or arc
          bundle to the working copy.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'revision' => array(
        'param' => 'revision_id',
        'paramtype' => 'complete',
        'help' =>
          "Apply changes from a Differential revision, using the most recent ".
          "diff that has been attached to it. You can run 'arc patch D12345' ".
          "as a shorthand.",
      ),
      'diff' => array(
        'param' => 'diff_id',
        'help' =>
          "Apply changes from a Differential diff. Normally you want to use ".
          "--revision to get the most recent changes, but you can ".
          "specifically apply an out-of-date diff or a diff which was never ".
          "attached to a revision by using this flag.",
      ),
      'arcbundle' => array(
        'param' => 'bundlefile',
        'paramtype' => 'file',
        'help' =>
          "Apply changes from an arc bundle generated with 'arc export'.",
      ),
      'patch' => array(
        'param' => 'patchfile',
        'paramtype' => 'file',
        'help' =>
          "Apply changes from a git patchfile or unified patchfile.",
      ),
      'encoding' => array(
        'param' => 'encoding',
        'help' =>
          "Attempt to convert non UTF-8 patch into specified encoding.",
      ),
      'update' => array(
        'supports' => array(
          'git', 'svn', 'hg'
        ),
        'help' =>
          "Update the local working copy before applying the patch.",
        'conflicts' => array(
          'nobranch' => true,
          'bookmark' => true,
        ),
      ),
      'nocommit' => array(
        'supports' => array(
          'git', 'hg'
        ),
        'help' =>
          "Normally under git/hg, if the patch is successful, the changes ".
          "are committed to the working copy. This flag prevents the commit.",
      ),
      'nobranch' => array(
        'supports' => array(
          'git', 'hg'
        ),
        'help' =>
          "Normally, a new branch (git) or bookmark (hg) is created and then ".
          "the patch is applied and committed in the new branch/bookmark. ".
          "This flag cherry-picks the resultant commit onto the original ".
          "branch and deletes the temporary branch.",
        'conflicts' => array(
          'update' => true,
        ),
      ),
      'force' => array(
        'help' =>
          "Do not run any sanity checks.",
      ),
      '*' => 'name',
    );
  }

  protected function didParseArguments() {
    $source = null;
    $requested = 0;
    if ($this->getArgument('revision')) {
      $source = self::SOURCE_REVISION;
      $requested++;
    }
    if ($this->getArgument('diff')) {
      $source = self::SOURCE_DIFF;
      $requested++;
    }
    if ($this->getArgument('arcbundle')) {
      $source = self::SOURCE_BUNDLE;
      $requested++;
    }
    if ($this->getArgument('patch')) {
      $source = self::SOURCE_PATCH;
      $requested++;
    }

    $use_revision_id = null;
    if ($this->getArgument('name')) {
      $namev = $this->getArgument('name');
      if (count($namev) > 1) {
        throw new ArcanistUsageException("Specify at most one revision name.");
      }
      $source = self::SOURCE_REVISION;
      $requested++;

      $use_revision_id = $this->normalizeRevisionID(head($namev));
    }

    if ($requested === 0) {
      throw new ArcanistUsageException(
        "Specify one of 'D12345', '--revision <revision_id>' (to select the ".
        "current changes attached to a Differential revision), ".
        "'--diff <diff_id>' (to select a specific, out-of-date diff or a ".
        "diff which is not attached to a revision), '--arcbundle <file>' ".
        "or '--patch <file>' to choose a patch source.");
    } else if ($requested > 1) {
      throw new ArcanistUsageException(
        "Options 'D12345', '--revision', '--diff', '--arcbundle' and ".
        "'--patch' are not compatible. Choose exactly one patch source.");
    }

    $this->source = $source;
    $this->sourceParam = nonempty(
      $use_revision_id,
      $this->getArgument($source));
  }

  public function requiresConduit() {
    return ($this->getSource() != self::SOURCE_PATCH);
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresWorkingCopy() {
    return true;
  }

  private function getSource() {
    return $this->source;
  }

  private function getSourceParam() {
    return $this->sourceParam;
  }

  private function shouldCommit() {
    $no_commit = $this->getArgument('nocommit', false);
    if ($no_commit) {
      return false;
    }

    return true;
  }

  private function canBranch() {
    $repository_api = $this->getRepositoryAPI();
    return ($repository_api instanceof ArcanistGitAPI) ||
           ($repository_api instanceof ArcanistMercurialAPI);
  }

  private function shouldBranch() {
    $no_branch = $this->getArgument('nobranch', false);
    if ($no_branch) {
      return false;
    }
    return true;
  }

  private function getBranchName(ArcanistBundle $bundle) {
    $branch_name    = null;
    $repository_api = $this->getRepositoryAPI();
    $revision_id    = $bundle->getRevisionID();
    $base_name      = "arcpatch";
    if ($revision_id) {
      $base_name .= "-D{$revision_id}";
    }

    $suffixes = array(null, '-1', '-2', '-3');
    foreach ($suffixes as $suffix) {
      $proposed_name = $base_name.$suffix;

      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $proposed_name);

      // no error means git rev-parse found a branch
      if (!$err) {
        echo phutil_console_format(
          "Branch name {$proposed_name} already exists; trying a new name.\n");
        continue;
      } else {
        $branch_name = $proposed_name;
        break;
      }
    }

    if (!$branch_name) {
      throw new Exception(
        "Arc was unable to automagically make a name for this patch.  ".
        "Please clean up your working copy and try again."
      );
    }

    return $branch_name;
  }

  private function getBookmarkName(ArcanistBundle $bundle) {
    $bookmark_name    = null;
    $repository_api = $this->getRepositoryAPI();
    $revision_id    = $bundle->getRevisionID();
    $base_name      = "arcpatch";
    if ($revision_id) {
      $base_name .= "-D{$revision_id}";
    }

    $suffixes = array(null, '-1', '-2', '-3');
    foreach ($suffixes as $suffix) {
      $proposed_name = $base_name.$suffix;

      list($err) = $repository_api->execManualLocal(
        'log -r %s',
        $proposed_name);

      // no error means hg log found a bookmark
      if (!$err) {
        echo phutil_console_format(
          "Bookmark name %s already exists; trying a new name.\n",
          $proposed_name);
        continue;
      } else {
        $bookmark_name = $proposed_name;
        break;
      }
    }

    if (!$bookmark_name) {
      throw new Exception(
        "Arc was unable to automagically make a name for this patch. ".
        "Please clean up your working copy and try again."
      );
    }

    return $bookmark_name;
  }

  private function hasBaseRevision(ArcanistBundle $bundle) {
    $base_revision = $bundle->getBaseRevision();
    $repository_api = $this->getRepositoryAPI();

    // verify the base revision is valid
    if ($repository_api instanceof ArcanistGitAPI) {
      // in a working copy that uses the git-svn bridge, the base revision might
      // be a svn uri instead of a git ref

      // NOTE: Use 'cat-file', not 'rev-parse --verify', because 'rev-parse'
      // always "verifies" any properly-formatted commit even if it does not
      // exist.
      list($err) = $repository_api->execManualLocal(
        'cat-file -t %s',
        $base_revision);
      return !$err;
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      return $repository_api->hasLocalCommit($base_revision);
    }

    return false;
  }

  private function createBranch(ArcanistBundle $bundle, $has_base_revision) {
    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistGitAPI) {
      $branch_name = $this->getBranchName($bundle);
      $base_revision = $bundle->getBaseRevision();

      if ($base_revision && $has_base_revision) {
        $repository_api->execxLocal(
          'checkout -b %s %s',
          $branch_name,
          $base_revision);
      } else {
        $repository_api->execxLocal(
          'checkout -b %s',
          $branch_name);
      }

      echo phutil_console_format(
        "Created and checked out branch %s.\n",
        $branch_name);
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $branch_name = $this->getBookmarkName($bundle);
      $base_revision = $bundle->getBaseRevision();

      if ($base_revision && $has_base_revision) {
        $base_revision = $repository_api->getCanonicalRevisionName(
          $base_revision);

        echo "Updating to the revision's base commit\n";
        $repository_api->execPassthru(
          'update %s',
          $base_revision);
      }

      $repository_api->execxLocal(
        'bookmark %s',
        $branch_name);

      echo phutil_console_format(
        "Created and checked out bookmark %s.\n",
        $branch_name);
    }

    return $branch_name;
  }

  private function shouldUpdateWorkingCopy() {
    return $this->getArgument('update', false);
  }

  private function updateWorkingCopy() {
    echo "Updating working copy...\n";
    $this->getRepositoryAPI()->updateWorkingCopy();
    echo "Done.\n";
  }

  public function run() {

    $source = $this->getSource();
    $param = $this->getSourceParam();
    try {
      switch ($source) {
        case self::SOURCE_PATCH:
          if ($param == '-') {
            $patch = @file_get_contents('php://stdin');
            if (!strlen($patch)) {
              throw new ArcanistUsageException(
                "Failed to read patch from stdin!");
            }
          } else {
            $patch = Filesystem::readFile($param);
          }
          $bundle = ArcanistBundle::newFromDiff($patch);
          break;
        case self::SOURCE_BUNDLE:
          $path = $this->getArgument('arcbundle');
          $bundle = ArcanistBundle::newFromArcBundle($path);
          break;
        case self::SOURCE_REVISION:
          $bundle = $this->loadRevisionBundleFromConduit(
            $this->getConduit(),
            $param);
          break;
        case self::SOURCE_DIFF:
          $bundle = $this->loadDiffBundleFromConduit(
            $this->getConduit(),
            $param);
          break;
      }
    } catch (ConduitClientException $ex) {
      if ($ex->getErrorCode() == 'ERR-INVALID-SESSION') {
        // Phabricator is not configured to allow anonymous access to
        // Differential.
        $this->authenticateConduit();
        return $this->run();
      } else {
        throw $ex;
      }
    }

    $try_encoding = nonempty($this->getArgument('encoding'), null);
    if (!$try_encoding) {
      if ($this->requiresConduit()) {
        try {
          $try_encoding = $this->getRepositoryEncoding();
        } catch (ConduitClientException $e) {
          $try_encoding = null;
        }
      }
    }

    if ($try_encoding) {
      $bundle->setEncoding($try_encoding);
    }

    $force = $this->getArgument('force', false);
    if ($force) {
      // force means don't do any sanity checks about the patch
    } else {
      $this->sanityCheck($bundle);
    }

    // we should update the working copy before we do ANYTHING else
    if ($this->shouldUpdateWorkingCopy()) {
      $this->updateWorkingCopy();
    }

    $repository_api = $this->getRepositoryAPI();

    $has_base_revision = $this->hasBaseRevision($bundle);
    if ($this->shouldCommit() &&
        $this->canBranch() &&
        ($this->shouldBranch() || $has_base_revision)) {

      if ($repository_api instanceof ArcanistGitAPI) {
        $original_branch = $repository_api->getBranchName();
      } else if ($repository_api instanceof ArcanistMercurialAPI) {
        $original_branch = $repository_api->getActiveBookmark();
      }

      // If we weren't on a branch, then record the ref we'll return to
      // instead.
      if ($original_branch === null) {
        if ($repository_api instanceof ArcanistGitAPI) {
          $original_branch = $repository_api->getCanonicalRevisionName('HEAD');
        } else if ($repository_api instanceof ArcanistMercurialAPI) {
          $original_branch = $repository_api->getCanonicalRevisionName('.');
        }
      }

      $new_branch = $this->createBranch($bundle, $has_base_revision);
    }

    if ($repository_api instanceof ArcanistSubversionAPI) {
      $patch_err = 0;

      $copies = array();
      $deletes = array();
      $patches = array();
      $propset = array();
      $adds = array();
      $symlinks = array();

      $changes = $bundle->getChanges();
      foreach ($changes as $change) {
        $type = $change->getType();
        $should_patch = true;

        $filetype = $change->getFileType();
        switch ($filetype) {
          case ArcanistDiffChangeType::FILE_SYMLINK:
            $should_patch = false;
            $symlinks[] = $change;
            break;
        }

        switch ($type) {
          case ArcanistDiffChangeType::TYPE_MOVE_AWAY:
          case ArcanistDiffChangeType::TYPE_MULTICOPY:
          case ArcanistDiffChangeType::TYPE_DELETE:
            $path = $change->getCurrentPath();
            $fpath = $repository_api->getPath($path);
            if (!@file_exists($fpath)) {
              $ok = phutil_console_confirm(
                "Patch deletes file '{$path}', but the file does not exist in ".
                "the working copy. Continue anyway?");
              if (!$ok) {
                throw new ArcanistUserAbortException();
              }
            } else {
              $deletes[] = $change->getCurrentPath();
            }
            $should_patch = false;
            break;
          case ArcanistDiffChangeType::TYPE_COPY_HERE:
          case ArcanistDiffChangeType::TYPE_MOVE_HERE:
            $path = $change->getOldPath();
            $fpath = $repository_api->getPath($path);
            if (!@file_exists($fpath)) {
              $cpath = $change->getCurrentPath();
              if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE) {
                $verbs = 'copies';
              } else {
                $verbs = 'moves';
              }
              $ok = phutil_console_confirm(
                "Patch {$verbs} '{$path}' to '{$cpath}', but source path ".
                "does not exist in the working copy. Continue anyway?");
              if (!$ok) {
                throw new ArcanistUserAbortException();
              }
            } else {
              $copies[] = array(
                $change->getOldPath(),
                $change->getCurrentPath());
            }
            break;
          case ArcanistDiffChangeType::TYPE_ADD:
            $adds[] = $change->getCurrentPath();
            break;
        }
        if ($should_patch) {
          $cbundle = ArcanistBundle::newFromChanges(array($change));
          $patches[$change->getCurrentPath()] = $cbundle->toUnifiedDiff();
          $prop_old = $change->getOldProperties();
          $prop_new = $change->getNewProperties();
          $props = $prop_old + $prop_new;
          foreach ($props as $key => $ignored) {
            if (idx($prop_old, $key) !== idx($prop_new, $key)) {
              $propset[$change->getCurrentPath()][$key] = idx($prop_new, $key);
            }
          }
        }
      }

      // Before we start doing anything, create all the directories we're going
      // to add files to if they don't already exist.
      foreach ($copies as $copy) {
        list($src, $dst) = $copy;
        $this->createParentDirectoryOf($dst);
      }

      foreach ($patches as $path => $patch) {
        $this->createParentDirectoryOf($path);
      }

      foreach ($adds as $add) {
        $this->createParentDirectoryOf($add);
      }

      // TODO: The SVN patch workflow likely does not work on windows because
      // of the (cd ...) stuff.

      foreach ($copies as $copy) {
        list($src, $dst) = $copy;
        passthru(
          csprintf(
            '(cd %s; svn cp %s %s)',
            $repository_api->getPath(),
            ArcanistSubversionAPI::escapeFileNameForSVN($src),
            ArcanistSubversionAPI::escapeFileNameForSVN($dst)));
      }

      foreach ($deletes as $delete) {
        passthru(
          csprintf(
            '(cd %s; svn rm %s)',
            $repository_api->getPath(),
            ArcanistSubversionAPI::escapeFileNameForSVN($delete)));
      }

      foreach ($symlinks as $symlink) {
        $link_target = $symlink->getSymlinkTarget();
        $link_path = $symlink->getCurrentPath();
        switch ($symlink->getType()) {
          case ArcanistDiffChangeType::TYPE_ADD:
          case ArcanistDiffChangeType::TYPE_CHANGE:
          case ArcanistDiffChangeType::TYPE_MOVE_HERE:
          case ArcanistDiffChangeType::TYPE_COPY_HERE:
            execx(
              '(cd %s && ln -sf %s %s)',
              $repository_api->getPath(),
              $link_target,
              $link_path);
            break;
        }
      }

      foreach ($patches as $path => $patch) {
        $err = null;
        if ($patch) {
          $tmp = new TempFile();
          Filesystem::writeFile($tmp, $patch);
          passthru(
            csprintf(
              '(cd %s; patch -p0 < %s)',
              $repository_api->getPath(),
              $tmp),
            $err);
        } else {
          passthru(
            csprintf(
              '(cd %s; touch %s)',
              $repository_api->getPath(),
              $path),
            $err);
        }
        if ($err) {
          $patch_err = max($patch_err, $err);
        }
      }

      foreach ($adds as $add) {
        passthru(
          csprintf(
            '(cd %s; svn add %s)',
            $repository_api->getPath(),
            ArcanistSubversionAPI::escapeFileNameForSVN($add)));
      }

      foreach ($propset as $path => $changes) {
        foreach ($changes as $prop => $value) {
          if ($prop == 'unix:filemode') {
            // Setting this property also changes the file mode.
            $prop = 'svn:executable';
            $value = (octdec($value) & 0111 ? 'on' : null);
          }
          if ($value === null) {
            passthru(
              csprintf(
                '(cd %s; svn propdel %s %s)',
                $repository_api->getPath(),
                $prop,
                ArcanistSubversionAPI::escapeFileNameForSVN($path)));
          } else {
            passthru(
              csprintf(
                '(cd %s; svn propset %s %s %s)',
                $repository_api->getPath(),
                $prop,
                $value,
                ArcanistSubversionAPI::escapeFileNameForSVN($path)));
          }
        }
      }

      if ($patch_err == 0) {
        echo phutil_console_format(
          "<bg:green>** OKAY **</bg> Successfully applied patch ".
          "to the working copy.\n");
      } else {
        echo phutil_console_format(
          "\n\n<bg:yellow>** WARNING **</bg> Some hunks could not be applied ".
          "cleanly by the unix 'patch' utility. Your working copy may be ".
          "different from the revision's base, or you may be in the wrong ".
          "subdirectory. You can export the raw patch file using ".
          "'arc export --unified', and then try to apply it by fiddling with ".
          "options to 'patch' (particularly, -p), or manually. The output ".
          "above, from 'patch', may be helpful in figuring out what went ".
          "wrong.\n");
      }

      return $patch_err;
    } else if ($repository_api instanceof ArcanistGitAPI) {

      $patchfile = new TempFile();
      Filesystem::writeFile($patchfile, $bundle->toGitPatch());

      $err = $repository_api->execPassthru(
        'apply --index --reject -- %s',
        $patchfile);

      if ($err) {
        echo phutil_console_format(
          "\n<bg:red>** Patch Failed! **</bg>\n");

        // NOTE: Git patches may fail if they change the case of a filename
        // (for instance, from 'example.c' to 'Example.c'). As of now, Git
        // can not apply these patches on case-insensitive filesystems and
        // there is no way to build a patch which works.

        throw new ArcanistUsageException("Unable to apply patch!");
      }

      if ($this->shouldCommit()) {
        if ($bundle->getFullAuthor()) {
          $author_cmd = csprintf('--author=%s', $bundle->getFullAuthor());
        } else {
          $author_cmd = '';
        }

        $commit_message = $this->getCommitMessage($bundle);
        $future = $repository_api->execFutureLocal(
          'commit -a %C -F -',
          $author_cmd);
        $future->write($commit_message);
        $future->resolvex();
        $verb = 'committed';
      } else {
        $verb = 'applied';
      }

      if ($this->shouldCommit() && $this->canBranch() &&
          !$this->shouldBranch() && $has_base_revision) {
        $repository_api->execxLocal('checkout %s', $original_branch);
        $ex = null;
        try {
          $repository_api->execxLocal('cherry-pick %s', $new_branch);
        } catch (Exception $ex) {}
        $repository_api->execxLocal('branch -D %s', $new_branch);
        if ($ex) {
          echo phutil_console_format(
            "\n<bg:red>** Cherry Pick Failed!**</bg>\n");
          throw $ex;
        }
      }

      echo phutil_console_format(
        "<bg:green>** OKAY **</bg> Successfully {$verb} patch.\n");
    } else if ($repository_api instanceof ArcanistMercurialAPI) {

      $future = $repository_api->execFutureLocal(
        'import --no-commit -');
      $future->write($bundle->toGitPatch());

      try {
        $future->resolvex();
      } catch (CommandException $ex) {
        echo phutil_console_format(
          "\n<bg:red>** Patch Failed! **</bg>\n");
        $stderr = $ex->getStdErr();
        if (preg_match('/case-folding collision/', $stderr)) {
          echo phutil_console_wrap(
            phutil_console_format(
              "\n<bg:yellow>** WARNING **</bg> This patch may have failed ".
              "because it attempts to change the case of a filename (for ".
              "instance, from 'example.c' to 'Example.c'). Mercurial cannot ".
              "apply patches like this on case-insensitive filesystems. You ".
              "must apply this patch manually.\n"));
        }
        throw $ex;
      }

      if ($this->shouldCommit()) {
        $author = coalesce($bundle->getFullAuthor(), $bundle->getAuthorName());
        if ($author !== null) {
          $author_cmd = csprintf('-u %s', $author);
        } else {
          $author_cmd = '';
        }

        $commit_message = $this->getCommitMessage($bundle);
        $future = $repository_api->execFutureLocal(
          'commit %C -l -',
          $author_cmd);
        $future->write($commit_message);
        $future->resolvex();

        if (!$this->shouldBranch() && $has_base_revision) {
          $original_rev = $repository_api->getCanonicalRevisionName(
            $original_branch);
          $current_parent = $repository_api->getCanonicalRevisionName(
            hgsprintf('%s^', $new_branch));

          $err = 0;
          if ($original_rev != $current_parent) {
            list($err) = $repository_api->execManualLocal(
              'rebase --dest %s --rev %s',
              hgsprintf('%s', $original_branch),
              hgsprintf('%s', $new_branch));
          }

          $repository_api->execxLocal('bookmark --delete %s', $new_branch);
          if ($err) {
            $repository_api->execManualLocal('rebase --abort');
            throw new ArcanistUsageException(phutil_console_format(
              "\n<bg:red>** Rebase onto $original_branch failed!**</bg>\n"));
          }
        }

        $verb = 'committed';
      } else {
        $verb = 'applied';
      }

      echo phutil_console_format(
        "<bg:green>** OKAY **</bg> Successfully {$verb} patch.\n");

    } else {
      throw new Exception('Unknown version control system.');
    }

    return 0;
  }

  private function getCommitMessage(ArcanistBundle $bundle) {
    $revision_id    = $bundle->getRevisionID();
    $commit_message = null;
    $prompt_message = null;

    // if we have a revision id the commit message is in differential

    // TODO: See T848 for the authenticated stuff.
    if ($revision_id && $this->isConduitAuthenticated()) {

      $conduit        = $this->getConduit();
      $commit_message = $conduit->callMethodSynchronous(
        'differential.getcommitmessage',
        array(
          'revision_id' => $revision_id,
        ));
      $prompt_message = "  Note arcanist failed to load the commit message ".
                        "from differential for revision D{$revision_id}.";
    }

    // no revision id or failed to fetch commit message so get it from the
    // user on the command line
    if (!$commit_message) {
      $template =
        "\n\n".
        "# Enter a commit message for this patch.  If you just want to apply ".
        "the patch to the working copy without committing, re-run arc patch ".
        "with the --nocommit flag.".
        $prompt_message.
        "\n";

      $commit_message = $this->newInteractiveEditor($template)
        ->setName('arcanist-patch-commit-message')
        ->editInteractively();

      $commit_message = ArcanistCommentRemover::removeComments($commit_message);
      if (!strlen(trim($commit_message))) {
        throw new ArcanistUserAbortException();
      }
    }

    return $commit_message;
  }

  public function getShellCompletions(array $argv) {
    // TODO: Pull open diffs from 'arc list'?
    return array('ARGUMENT');
  }

  /**
   * Do the best we can to prevent PEBKAC and id10t issues.
   */
  private function sanityCheck(ArcanistBundle $bundle) {
    $repository_api = $this->getRepositoryAPI();

    // Require clean working copy
    $this->requireCleanWorkingCopy();

    // Check to see if the bundle's project id matches the working copy
    // project id
    $bundle_project_id = $bundle->getProjectID();
    $working_copy_project_id = $this->getWorkingCopy()->getProjectID();
    if (empty($bundle_project_id)) {
      // this means $source is SOURCE_PATCH || SOURCE_BUNDLE w/ $version = 0
      // they don't come with a project id so just do nothing
    } else if ($bundle_project_id != $working_copy_project_id) {
      if ($working_copy_project_id) {
        $issue =
          "This patch is for the '{$bundle_project_id}' project,  but the ".
          "working copy belongs to the '{$working_copy_project_id}' project.";
      } else {
        $issue =
          "This patch is for the '{$bundle_project_id}' project, but the ".
          "working copy does not have an '.arcconfig' file to identify which ".
          "project it belongs to.";
      }
      $ok = phutil_console_confirm(
        "{$issue} Still try to apply the patch?",
        $default_no = false);
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    // Check to see if the bundle's base revision matches the working copy
    // base revision
    if ($repository_api->supportsLocalCommits()) {
      $bundle_base_rev = $bundle->getBaseRevision();
      if (empty($bundle_base_rev)) {
        // this means $source is SOURCE_PATCH || SOURCE_BUNDLE w/ $version < 2
        // they don't have a base rev so just do nothing
        $commit_exists = true;
      } else {
        $commit_exists =
          $repository_api->hasLocalCommit($bundle_base_rev);
      }
      if (!$commit_exists) {
        // we have a problem...! lots of work because we need to ask
        // differential for revision information for these base revisions
        // to improve our error message.
        $bundle_base_rev_str = null;
        $source_base_rev     = $repository_api->getWorkingCopyRevision();
        $source_base_rev_str = null;

        if ($repository_api instanceof ArcanistGitAPI) {
          $hash_type = ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT;
        } else if ($repository_api instanceof ArcanistMercurialAPI) {
          $hash_type = ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT;
        } else {
          $hash_type = null;
        }

        if ($hash_type) {
          // 2 round trips because even though we could send off one query
          // we wouldn't be able to tell which revisions were for which hash
          $hash = array($hash_type, $bundle_base_rev);
          $bundle_revision = $this->loadRevisionFromHash($hash);
          $hash = array($hash_type, $source_base_rev);
          $source_revision = $this->loadRevisionFromHash($hash);

          if ($bundle_revision) {
            $bundle_base_rev_str = $bundle_base_rev .
                                   ' \ D' . $bundle_revision['id'];
          }
          if ($source_revision) {
            $source_base_rev_str = $source_base_rev .
                                   ' \ D' . $source_revision['id'];
          }
        }
        $bundle_base_rev_str = nonempty($bundle_base_rev_str,
                                        $bundle_base_rev);
        $source_base_rev_str = nonempty($source_base_rev_str,
                                        $source_base_rev);

        $ok = phutil_console_confirm(
          "This diff is against commit {$bundle_base_rev_str}, but the ".
          "commit is nowhere in the working copy. Try to apply it against ".
          "the current working copy state? ({$source_base_rev_str})",
          $default_no = false);
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    // TODO -- more sanity checks here
  }

  /**
   * Create parent directories one at a time, since we need to "svn add" each
   * one. (Technically we could "svn add" just the topmost new directory.)
   */
  private function createParentDirectoryOf($path) {
    $repository_api = $this->getRepositoryAPI();
    $dir = dirname($path);
    if (Filesystem::pathExists($dir)) {
      return;
    } else {
      // Make sure the parent directory exists before we make this one.
      $this->createParentDirectoryOf($dir);
      execx(
        '(cd %s && mkdir %s)',
        $repository_api->getPath(),
        $dir);
      passthru(
        csprintf(
          '(cd %s && svn add %s)',
          $repository_api->getPath(),
          $dir));
    }
  }

  private function loadRevisionFromHash($hash) {
    // TODO -- de-hack this as permissions become more clear with things
    // like T848 (add scope to OAuth)
    if (!$this->isConduitAuthenticated()) {
      return null;
    }

    $conduit = $this->getConduit();

    $revisions = $conduit->callMethodSynchronous(
      'differential.query',
      array(
        'commitHashes' => array($hash),
      ));


    // grab the latest closed revision only
    $found_revision = null;
    $revisions = isort($revisions, 'dateModified');
    foreach ($revisions as $revision) {
      if ($revision['status'] == ArcanistDifferentialRevisionStatus::CLOSED) {
        $found_revision = $revision;
      }
    }
    return $found_revision;
  }
}
