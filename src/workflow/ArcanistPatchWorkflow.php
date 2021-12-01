<?php

/**
 * Applies changes from Differential or a file to the working copy.
 */
final class ArcanistPatchWorkflow extends ArcanistWorkflow {

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
        'help' => pht(
          "Apply changes from a Differential revision, using the most recent ".
          "diff that has been attached to it. You can run '%s' as a shorthand.",
          'arc patch D12345'),
      ),
      'diff' => array(
        'param' => 'diff_id',
        'help' => pht(
          'Apply changes from a Differential diff. Normally you want to use '.
          '%s to get the most recent changes, but you can specifically apply '.
          'an out-of-date diff or a diff which was never attached to a '.
          'revision by using this flag.',
          '--revision'),
      ),
      'arcbundle' => array(
        'param' => 'bundlefile',
        'paramtype' => 'file',
        'help' => pht(
          "Apply changes from an arc bundle generated with '%s'.",
          'arc export'),
      ),
      'patch' => array(
        'param' => 'patchfile',
        'paramtype' => 'file',
        'help' => pht(
          'Apply changes from a git patchfile or unified patchfile.'),
      ),
      'encoding' => array(
        'param' => 'encoding',
        'help' => pht(
          'Attempt to convert non UTF-8 patch into specified encoding.'),
      ),
      'update' => array(
        'supports' => array('git', 'svn', 'hg'),
        'help' => pht(
          'Update the local working copy before applying the patch.'),
        'conflicts' => array(
          'nobranch' => true,
          'bookmark' => true,
        ),
      ),
      'nocommit' => array(
        'supports' => array('git', 'hg'),
        'help' => pht(
          'Normally under git/hg, if the patch is successful, the changes '.
          'are committed to the working copy. This flag prevents the commit.'),
      ),
      'skip-dependencies' => array(
        'supports' => array('git', 'hg'),
        'help' => pht(
          'Normally, if a patch has dependencies that are not present in the '.
          'working copy, arc tries to apply them as well. This flag prevents '.
          'such work.'),
      ),
      'nobranch' => array(
        'supports' => array('git', 'hg'),
        'help' => pht(
          'Normally, a new branch (git) or bookmark (hg) is created and then '.
          'the patch is applied and committed in the new branch/bookmark. '.
          'This flag cherry-picks the resultant commit onto the original '.
          'branch and deletes the temporary branch.'),
        'conflicts' => array(
          'update' => true,
        ),
      ),
      'force' => array(
        'help' => pht('Do not run any sanity checks.'),
      ),
      '*' => 'name',
    );
  }

  protected function didParseArguments() {
    $arguments = array(
      'revision' => self::SOURCE_REVISION,
      'diff' => self::SOURCE_DIFF,
      'arcbundle' => self::SOURCE_BUNDLE,
      'patch' => self::SOURCE_PATCH,
      'name' => self::SOURCE_REVISION,
    );

    $sources = array();
    foreach ($arguments as $key => $source_type) {
      $value = $this->getArgument($key);
      if (!$value) {
        continue;
      }

      switch ($key) {
        case 'revision':
          $value = $this->normalizeRevisionID($value);
          break;
        case 'name':
          if (count($value) > 1) {
            throw new ArcanistUsageException(
              pht('Specify at most one revision name.'));
          }
          $value = $this->normalizeRevisionID(head($value));
          break;
      }

      $sources[] = array(
        $source_type,
        $value,
      );
    }

    if (!$sources) {
      throw new ArcanistUsageException(
        pht(
          'You must specify changes to apply to the working copy with '.
          '"D12345", "--revision", "--diff", "--arcbundle", or "--patch".'));
    }

    if (count($sources) > 1) {
      throw new ArcanistUsageException(
        pht(
          'Options "D12345", "--revision", "--diff", "--arcbundle" and '.
          '"--patch" are mutually exclusive. Choose exactly one patch '.
          'source.'));
    }

    $source = head($sources);

    $this->source = $source[0];
    $this->sourceParam = $source[1];
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
    return !$this->getArgument('nocommit', false);
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
    $base_name      = 'arcpatch';
    if ($revision_id) {
      $base_name .= "-D{$revision_id}";
    }

    $suffixes = array(null, '_1', '_2', '_3');
    foreach ($suffixes as $suffix) {
      $proposed_name = $base_name.$suffix;

      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $proposed_name);

      // no error means git rev-parse found a branch
      if (!$err) {
        echo phutil_console_format(
          "%s\n",
          pht(
            'Branch name %s already exists; trying a new name.',
            $proposed_name));
        continue;
      } else {
        $branch_name = $proposed_name;
        break;
      }
    }

    if (!$branch_name) {
      throw new Exception(
        pht(
          'Arc was unable to automagically make a name for this patch. '.
          'Please clean up your working copy and try again.'));
    }

    return $branch_name;
  }

  private function getBookmarkName(ArcanistBundle $bundle) {
    $bookmark_name  = null;
    $repository_api = $this->getRepositoryAPI();
    $revision_id    = $bundle->getRevisionID();
    $base_name      = 'arcpatch';
    if ($revision_id) {
      $base_name .= "-D{$revision_id}";
    }

    $suffixes = array(null, '-1', '-2', '-3');
    foreach ($suffixes as $suffix) {
      $proposed_name = $base_name.$suffix;

      list($err) = $repository_api->execManualLocal(
        'log -r %s',
        hgsprintf('%s', $proposed_name));

      // no error means hg log found a bookmark
      if (!$err) {
        echo phutil_console_format(
          "%s\n",
          pht(
            'Bookmark name %s already exists; trying a new name.',
            $proposed_name));
        continue;
      } else {
        $bookmark_name = $proposed_name;
        break;
      }
    }

    if (!$bookmark_name) {
      throw new Exception(
        pht(
          'Arc was unable to automagically make a name for this patch. '.
          'Please clean up your working copy and try again.'));
    }

    return $bookmark_name;
  }

  private function createBranch(ArcanistBundle $bundle, $has_base_revision) {
    $repository_api = $this->getRepositoryAPI();
    if ($repository_api instanceof ArcanistGitAPI) {
      $branch_name = $this->getBranchName($bundle);
      $base_revision = $bundle->getBaseRevision();

      if ($base_revision && $has_base_revision) {
        $base_revision = $repository_api->getCanonicalRevisionName(
          $base_revision);
        $repository_api->execxLocal(
          'checkout -b %s %s',
          $branch_name,
          $base_revision);
      } else {
        $repository_api->execxLocal('checkout -b %s', $branch_name);
      }

      // Synchronize submodule state, since the checkout may have modified
      // submodule references. See PHI1083.

      // Note that newer versions of "git checkout" include a
      // "--recurse-submodules" flag which accomplishes this goal a little
      // more simply. For now, use the more compatible form.
      $repository_api->execPassthru('submodule update --init --recursive');

      echo phutil_console_format(
        "%s\n",
        pht(
          'Created and checked out branch %s.',
          $branch_name));
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $branch_name = $this->getBookmarkName($bundle);
      $base_revision = $bundle->getBaseRevision();

      if ($base_revision && $has_base_revision) {
        $base_revision = $repository_api->getCanonicalRevisionName(
          $base_revision);

        echo pht("Updating to the revision's base commit")."\n";
        $repository_api->execPassthru('update %s', $base_revision);
      }

      $repository_api->execxLocal('bookmark %s', $branch_name);

      echo phutil_console_format(
        "%s\n",
        pht(
          'Created and checked out bookmark %s.',
          $branch_name));
    }

    return $branch_name;
  }

  private function shouldApplyDependencies() {
    return !$this->getArgument('skip-dependencies', false);
  }

  private function shouldUpdateWorkingCopy() {
    return $this->getArgument('update', false);
  }

  private function updateWorkingCopy() {
    echo pht('Updating working copy...')."\n";
    $this->getRepositoryAPI()->updateWorkingCopy();
    echo pht('Done.')."\n";
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
                pht('Failed to read patch from stdin!'));
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

    $sanity_check = !$this->getArgument('force', false);

    // we should update the working copy before we do ANYTHING else to
    // the working copy
    if ($this->shouldUpdateWorkingCopy()) {
      $this->updateWorkingCopy();
    }

    if ($sanity_check) {
      $this->requireCleanWorkingCopy();
    }

    $repository_api = $this->getRepositoryAPI();
    $has_base_revision = $repository_api->hasLocalCommit(
      $bundle->getBaseRevision());
    if (!$has_base_revision) {
      if ($repository_api instanceof ArcanistGitAPI) {
        echo phutil_console_format(
          "<bg:blue>** %s **</bg> %s\n",
          pht('INFO'),
          pht('Base commit is not in local repository; trying to fetch.'));
        $repository_api->execManualLocal('fetch --quiet --all');
        $has_base_revision = $repository_api->hasLocalCommit(
          $bundle->getBaseRevision());
      }
    }

    if ($this->canBranch() &&
         ($this->shouldBranch() ||
         ($this->shouldCommit() && $has_base_revision))) {

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
    if (!$has_base_revision && $this->shouldApplyDependencies()) {
      $this->applyDependencies($bundle);
    }

    if ($sanity_check) {
      $this->sanityCheck($bundle);
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
                pht(
                  "Patch deletes file '%s', but the file does not exist in ".
                  "the working copy. Continue anyway?",
                  $path));
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
                $verbs = pht('copies');
              } else {
                $verbs = pht('moves');
              }
              $ok = phutil_console_confirm(
                pht(
                  "Patch %s '%s' to '%s', but source path does not exist ".
                  "in the working copy. Continue anyway?",
                  $verbs,
                  $path,
                  $cpath));
              if (!$ok) {
                throw new ArcanistUserAbortException();
              }
            } else {
              $copies[] = array(
                $change->getOldPath(),
                $change->getCurrentPath(),
              );
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
          "<bg:green>** %s **</bg> %s\n",
          pht('OKAY'),
          pht('Successfully applied patch to the working copy.'));
      } else {
        echo phutil_console_format(
          "\n\n<bg:yellow>** %s **</bg> %s\n",
          pht('WARNING'),
          pht(
            "Some hunks could not be applied cleanly by the unix '%s' ".
            "utility. Your working copy may be different from the revision's ".
            "base, or you may be in the wrong subdirectory. You can export ".
            "the raw patch file using '%s', and then try to apply it by ".
            "fiddling with options to '%s' (particularly, %s), or manually. ".
            "The output above, from '%s', may be helpful in ".
            "figuring out what went wrong.",
            'patch',
            'arc export --unified',
            'patch',
            '-p',
            'patch'));
      }

      return $patch_err;
    } else if ($repository_api instanceof ArcanistGitAPI) {

      $patchfile = new TempFile();
      Filesystem::writeFile($patchfile, $bundle->toGitPatch());

      $passthru = new PhutilExecPassthru(
        'git apply --whitespace nowarn --index --reject -- %s',
        $patchfile);
      $passthru->setCWD($repository_api->getPath());
      $err = $passthru->resolve();

      if ($err) {
        echo phutil_console_format(
          "\n<bg:red>** %s **</bg>\n",
          pht('Patch Failed!'));

        // NOTE: Git patches may fail if they change the case of a filename
        // (for instance, from 'example.c' to 'Example.c'). As of now, Git
        // can not apply these patches on case-insensitive filesystems and
        // there is no way to build a patch which works.

        throw new ArcanistUsageException(pht('Unable to apply patch!'));
      }

      // See PHI1083 and PHI648. If the patch applied changes to submodules,
      // it only updates the submodule pointer, not the actual submodule. We're
      // left with the pointer update staged in the index, and the unmodified
      // submodule on disk.

      // If we then "git commit --all" or "git add --all", the unmodified
      // submodule on disk is added to the index as a change, which effectively
      // undoes the patch we just applied and reverts the submodule back to
      // the previous state.

      // To avoid this, do a submodule update before we continue.

      // We could also possibly skip the "--all" flag so we don't have to do
      // this submodule update, but we want to leave the working copy in a
      // clean state anyway, so we're going to have to do an update at some
      // point. This usually doesn't cost us anything.
      $repository_api->execPassthru('submodule update --init --recursive');

      if ($this->shouldCommit()) {
        $flags = array();
        if ($bundle->getFullAuthor()) {
          $flags[] = csprintf('--author=%s', $bundle->getFullAuthor());
        }

        $commit_message = $this->getCommitMessage($bundle);

        $future = $repository_api->execFutureLocal(
          'commit -a %Ls -F - --no-verify',
          $flags);
        $future->write($commit_message);
        $future->resolvex();

        $this->writeOkay(
          pht('COMMITTED'),
          pht('Successfully committed patch.'));
      } else {
        $this->writeOkay(
          pht('APPLIED'),
          pht('Successfully applied patch.'));
      }

      if ($this->canBranch() &&
          !$this->shouldBranch() &&
          $this->shouldCommit() && $has_base_revision) {

        // See PHI1083 and PHI648. Synchronize submodule state after mutating
        // the working copy.

        $repository_api->execxLocal('checkout %s --', $original_branch);
        $repository_api->execPassthru('submodule update --init --recursive');

        $ex = null;
        try {
          $repository_api->execxLocal('cherry-pick -- %s', $new_branch);
          $repository_api->execPassthru('submodule update --init --recursive');
        } catch (Exception $ex) {
          // do nothing
        }

        $repository_api->execxLocal('branch -D -- %s', $new_branch);

        if ($ex) {
          echo phutil_console_format(
            "\n<bg:red>** %s**</bg>\n",
            pht('Cherry Pick Failed!'));
          throw $ex;
        }
      }

    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $future = $repository_api->execFutureLocal('import --no-commit -');
      $future->write($bundle->toGitPatch());

      try {
        $future->resolvex();
      } catch (CommandException $ex) {
        echo phutil_console_format(
          "\n<bg:red>** %s **</bg>\n",
          pht('Patch Failed!'));
        $stderr = $ex->getStderr();
        if (preg_match('/case-folding collision/', $stderr)) {
          echo phutil_console_wrap(
            phutil_console_format(
              "\n<bg:yellow>** %s **</bg> %s\n",
              pht('WARNING'),
              pht(
                "This patch may have failed because it attempts to change ".
                "the case of a filename (for instance, from '%s' to '%s'). ".
                "Mercurial cannot apply patches like this on case-insensitive ".
                "filesystems. You must apply this patch manually.",
                'example.c',
                'Example.c')));
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
            throw new ArcanistUsageException(
              phutil_console_format(
                "\n<bg:red>** %s**</bg>\n",
                pht('Rebase onto %s failed!', $original_branch)));
          }
        }

        $verb = pht('committed');
      } else {
        $verb = pht('applied');
      }

      echo phutil_console_format(
        "<bg:green>** %s **</bg> %s\n",
        pht('OKAY'),
        pht('Successfully %s patch.', $verb));
    } else {
      throw new Exception(pht('Unknown version control system.'));
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
      $prompt_message = pht(
        '  Note arcanist failed to load the commit message '.
        'from differential for revision %s.',
        "D{$revision_id}");
    }

    // no revision id or failed to fetch commit message so get it from the
    // user on the command line
    if (!$commit_message) {
      $template = sprintf(
        "\n\n# %s%s\n",
        pht(
          'Enter a commit message for this patch. If you just want to apply '.
          'the patch to the working copy without committing, re-run arc patch '.
          'with the %s flag.',
          '--nocommit'),
        $prompt_message);

      $commit_message = $this->newInteractiveEditor($template)
        ->setName('arcanist-patch-commit-message')
        ->setTaskMessage(pht(
          'Supply a commit message for this patch, then save and exit.'))
        ->editInteractively();

      $commit_message = ArcanistCommentRemover::removeComments($commit_message);
      if (!strlen(trim($commit_message))) {
        throw new ArcanistUserAbortException();
      }
    }

    return $commit_message;
  }

  protected function getShellCompletions(array $argv) {
    // TODO: Pull open diffs from 'arc list'?
    return array('ARGUMENT');
  }

  private function applyDependencies(ArcanistBundle $bundle) {
    // check for (and automagically apply on the user's be-hest) any revisions
    // this patch depends on
    $graph = $this->buildDependencyGraph($bundle);
    if ($graph) {
      $start_phid = $graph->getStartPHID();
      $cycle_phids = $graph->detectCycles($start_phid);
      if ($cycle_phids) {
        $phids = array_keys($graph->getNodes());
        $issue = pht(
          'The dependencies for this patch have a cycle. Applying them '.
          'is not guaranteed to work. Continue anyway?');
        $okay = phutil_console_confirm($issue, true);
      } else {
        $phids = $graph->getNodesInTopologicalOrder();
        $phids = array_reverse($phids);
        $okay = true;
      }

      if (!$okay) {
        return;
      }

      $dep_on_revs = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'phids' => $phids,
        ));
      $revs = array();
      foreach ($dep_on_revs as $dep_on_rev) {
        $revs[$dep_on_rev['phid']] = 'D'.$dep_on_rev['id'];
      }
      // order them in case we got a topological sort earlier
      $revs = array_select_keys($revs, $phids);
      if (!empty($revs)) {
        $base_args = array(
          '--force',
          '--skip-dependencies',
          '--nobranch',
        );
        if (!$this->shouldCommit()) {
          $base_args[] = '--nocommit';
        }

        foreach ($revs as $phid => $diff_id) {
          // we'll apply this, the actual patch, later
          // this should be the last in the list
          if ($phid == $start_phid) {
            continue;
          }
          $args = $base_args;
          $args[] = $diff_id;
          $apply_workflow = $this->buildChildWorkflow(
            'patch',
            $args);
          $apply_workflow->run();
        }
      }
    }
  }

  /**
   * Do the best we can to prevent PEBKAC and id10t issues.
   */
  private function sanityCheck(ArcanistBundle $bundle) {
    $repository_api = $this->getRepositoryAPI();

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
            $bundle_base_rev_str = $bundle_base_rev.
              ' \ D'.$bundle_revision['id'];
          }
          if ($source_revision) {
            $source_base_rev_str = $source_base_rev.
              ' \ D'.$source_revision['id'];
          }
        }
        $bundle_base_rev_str = nonempty(
          $bundle_base_rev_str,
          $bundle_base_rev);
        $source_base_rev_str = nonempty(
          $source_base_rev_str,
          $source_base_rev);

        $ok = phutil_console_confirm(
          pht(
            'This diff is against commit %s, but the commit is nowhere '.
            'in the working copy. Try to apply it against the current '.
            'working copy state? (%s)',
            $bundle_base_rev_str,
            $source_base_rev_str),
          $default_no = false);
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }
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

  private function buildDependencyGraph(ArcanistBundle $bundle) {
    $graph = null;
    if ($this->getRepositoryAPI() instanceof ArcanistSubversionAPI) {
      return $graph;
    }
    $revision_id = $bundle->getRevisionID();
    if ($revision_id) {
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if ($revisions) {
        $revision = head($revisions);
        $rev_auxiliary = idx($revision, 'auxiliary', array());
        $phids = idx($rev_auxiliary, 'phabricator:depends-on', array());
        if ($phids) {
          $revision_phid = $revision['phid'];
          $graph = id(new ArcanistDifferentialDependencyGraph())
            ->setConduit($this->getConduit())
            ->setRepositoryAPI($this->getRepositoryAPI())
            ->setStartPHID($revision_phid)
            ->addNodes(array($revision_phid => $phids))
            ->loadGraph();
        }
      }
    }

    return $graph;
  }

}
