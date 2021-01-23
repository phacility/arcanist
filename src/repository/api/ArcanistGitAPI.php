<?php

/**
 * Interfaces with Git working copies.
 */
final class ArcanistGitAPI extends ArcanistRepositoryAPI {

  private $repositoryHasNoCommits = false;
  const SEARCH_LENGTH_FOR_PARENT_REVISIONS = 16;

  /**
   * For the repository's initial commit, 'git diff HEAD^' and similar do
   * not work. Using this instead does work; it is the hash of the empty tree.
   */
  const GIT_MAGIC_ROOT_COMMIT = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

  private $symbolicHeadCommit;
  private $resolvedHeadCommit;

  protected function buildLocalFuture(array $argv) {
    $argv[0] = 'git '.$argv[0];

    return newv('ExecFuture', $argv)
      ->setCWD($this->getPath());
  }

  public function newPassthru($pattern /* , ... */) {
    $args = func_get_args();

    static $git = null;
    if ($git === null) {
      if (phutil_is_windows()) {
        // NOTE: On Windows, phutil_passthru() uses 'bypass_shell' because
        // everything goes to hell if we don't. We must provide an absolute
        // path to Git for this to work properly.
        $git = Filesystem::resolveBinary('git');
        $git = csprintf('%s', $git);
      } else {
        $git = 'git';
      }
    }

    $args[0] = $git.' '.$args[0];

    return newv('PhutilExecPassthru', $args)
      ->setCWD($this->getPath());
  }

  public function getSourceControlSystemName() {
    return 'git';
  }

  public function getGitVersion() {
    static $version = null;
    if ($version === null) {
      list($stdout) = $this->execxLocal('--version');
      $version = rtrim(str_replace('git version ', '', $stdout));
    }
    return $version;
  }

  public function getMetadataPath() {
    static $path = null;
    if ($path === null) {
      list($stdout) = $this->execxLocal('rev-parse --git-dir');
      $path = rtrim($stdout, "\n");
      // the output of git rev-parse --git-dir is an absolute path, unless
      // the cwd is the root of the repository, in which case it uses the
      // relative path of .git. If we get this relative path, turn it into
      // an absolute path.
      if ($path === '.git') {
        $path = $this->getPath('.git');
      }
    }
    return $path;
  }

  public function getHasCommits() {
    return !$this->repositoryHasNoCommits;
  }

  /**
   * Tests if a child commit is descendant of a parent commit.
   * If child and parent are the same, it returns false.
   * @param Child commit SHA.
   * @param Parent commit SHA.
   * @return bool True if the child is a descendant of the parent.
   */
  private function isDescendant($child, $parent) {
    list($common_ancestor) = $this->execxLocal(
      'merge-base -- %s %s',
      $child,
      $parent);
    $common_ancestor = trim($common_ancestor);

    return ($common_ancestor == $parent) && ($common_ancestor != $child);
  }

  public function getLocalCommitInformation() {
    if ($this->repositoryHasNoCommits) {
      // Zero commits.
      throw new Exception(
        pht(
          "You can't get local commit information for a repository with no ".
          "commits."));
    } else if ($this->getBaseCommit() == self::GIT_MAGIC_ROOT_COMMIT) {
      // One commit.
      $against = 'HEAD';
    } else {

      // 2..N commits. We include commits reachable from HEAD which are
      // not reachable from the base commit; this is consistent with user
      // expectations even though it is not actually the diff range.
      // Particularly:
      //
      //    |
      //    D <----- master branch
      //    |
      //    C  Y <- feature branch
      //    | /|
      //    B  X
      //    | /
      //    A
      //    |
      //
      // If "A, B, C, D" are master, and the user is at Y, when they run
      // "arc diff B" they want (and get) a diff of B vs Y, but they think about
      // this as being the commits X and Y. If we log "B..Y", we only show
      // Y. With "Y --not B", we show X and Y.


      if ($this->symbolicHeadCommit !== null) {
        $base_commit = $this->getBaseCommit();
        $resolved_base = $this->resolveCommit($base_commit);

        $head_commit = $this->symbolicHeadCommit;
        $resolved_head = $this->getHeadCommit();

        if (!$this->isDescendant($resolved_head, $resolved_base)) {
          // NOTE: Since the base commit will have been resolved as the
          // merge-base of the specified base and the specified HEAD, we can't
          // easily tell exactly what's wrong with the range.

          // For example, `arc diff HEAD --head HEAD^^^` is invalid because it
          // is reversed, but resolving the commit "HEAD" will compute its
          // merge-base with "HEAD^^^", which is "HEAD^^^", so the range will
          // appear empty.

          throw new ArcanistUsageException(
            pht(
              'The specified commit range is empty, backward or invalid: the '.
              'base (%s) is not an ancestor of the head (%s). You can not '.
              'diff an empty or reversed commit range.',
              $base_commit,
              $head_commit));
        }
      }

      $against = csprintf(
        '%s --not %s',
        $this->getHeadCommit(),
        $this->getBaseCommit());
    }

    // NOTE: Windows escaping of "%" symbols apparently is inherently broken;
    // when passed through escapeshellarg() they are replaced with spaces.

    // TODO: Learn how cmd.exe works and find some clever workaround?

    // NOTE: If we use "%x00", output is truncated in Windows.

    list($info) = $this->execxLocal(
      phutil_is_windows()
        ? 'log %C --format=%C --'
        : 'log %C --format=%s --',
      $against,
      // NOTE: "%B" is somewhat new, use "%s%n%n%b" instead.
      '%H%x01%T%x01%P%x01%at%x01%an%x01%aE%x01%s%x01%s%n%n%b%x02');

    $commits = array();

    $info = trim($info, " \n\2");
    if (!strlen($info)) {
      return array();
    }

    $info = explode("\2", $info);
    foreach ($info as $line) {
      list($commit, $tree, $parents, $time, $author, $author_email,
        $title, $message) = explode("\1", trim($line), 8);
      $message = rtrim($message);

      $commits[$commit] = array(
        'commit'  => $commit,
        'tree'    => $tree,
        'parents' => array_filter(explode(' ', $parents)),
        'time'    => $time,
        'author'  => $author,
        'summary' => $title,
        'message' => $message,
        'authorEmail' => $author_email,
      );
    }

    return $commits;
  }

  protected function buildBaseCommit($symbolic_commit) {
    if ($symbolic_commit !== null) {
      if ($symbolic_commit == self::GIT_MAGIC_ROOT_COMMIT) {
        $this->setBaseCommitExplanation(
          pht('you explicitly specified the empty tree.'));
        return $symbolic_commit;
      }

      list($err, $merge_base) = $this->execManualLocal(
        'merge-base -- %s %s',
        $symbolic_commit,
        $this->getHeadCommit());
      if ($err) {
        throw new ArcanistUsageException(
          pht(
            "Unable to find any git commit named '%s' in this repository.",
            $symbolic_commit));
      }

      if ($this->symbolicHeadCommit === null) {
        $this->setBaseCommitExplanation(
          pht(
            "it is the merge-base of the explicitly specified base commit ".
            "'%s' and HEAD.",
            $symbolic_commit));
      } else {
        $this->setBaseCommitExplanation(
          pht(
            "it is the merge-base of the explicitly specified base commit ".
            "'%s' and the explicitly specified head commit '%s'.",
            $symbolic_commit,
            $this->symbolicHeadCommit));
      }

      return trim($merge_base);
    }

    // Detect zero-commit or one-commit repositories. There is only one
    // relative-commit value that makes any sense in these repositories: the
    // empty tree.
    list($err) = $this->execManualLocal('rev-parse --verify HEAD^');
    if ($err) {
      list($err) = $this->execManualLocal('rev-parse --verify HEAD');
      if ($err) {
        $this->repositoryHasNoCommits = true;
      }

      if ($this->repositoryHasNoCommits) {
        $this->setBaseCommitExplanation(pht('the repository has no commits.'));
      } else {
        $this->setBaseCommitExplanation(
          pht('the repository has only one commit.'));
      }

      return self::GIT_MAGIC_ROOT_COMMIT;
    }

    if ($this->getBaseCommitArgumentRules() ||
        $this->getConfigurationManager()->getConfigFromAnySource('base')) {
      $base = $this->resolveBaseCommit();
      if (!$base) {
        throw new ArcanistUsageException(
          pht(
            "None of the rules in your 'base' configuration matched a valid ".
            "commit. Adjust rules or specify which commit you want to use ".
            "explicitly."));
      }
      return $base;
    }

    $do_write = false;
    $default_relative = null;
    $working_copy = $this->getWorkingCopyIdentity();
    if ($working_copy) {
      $default_relative = $working_copy->getProjectConfig(
        'git.default-relative-commit');
      $this->setBaseCommitExplanation(
        pht(
          "it is the merge-base of '%s' and HEAD, as specified in '%s' in ".
          "'%s'. This setting overrides other settings.",
          $default_relative,
          'git.default-relative-commit',
          '.arcconfig'));
    }

    if (!$default_relative) {
      list($err, $upstream) = $this->execManualLocal(
        'rev-parse --abbrev-ref --symbolic-full-name %s',
        '@{upstream}');

      if (!$err) {
        $default_relative = trim($upstream);
        $this->setBaseCommitExplanation(
          pht(
            "it is the merge-base of '%s' (the Git upstream ".
            "of the current branch) HEAD.",
            $default_relative));
      }
    }

    if (!$default_relative) {
      $default_relative = $this->readScratchFile('default-relative-commit');
      $default_relative = trim($default_relative);
      if ($default_relative) {
        $this->setBaseCommitExplanation(
          pht(
            "it is the merge-base of '%s' and HEAD, as specified in '%s'.",
            $default_relative,
            '.git/arc/default-relative-commit'));
      }
    }

    if (!$default_relative) {

      // TODO: Remove the history lesson soon.

      echo phutil_console_format(
        "<bg:green>** %s **</bg>\n\n",
        pht('Select a Default Commit Range'));
      echo phutil_console_wrap(
        pht(
          "You're running a command which operates on a range of revisions ".
          "(usually, from some revision to HEAD) but have not specified the ".
          "revision that should determine the start of the range.\n\n".
          "Previously, arc assumed you meant '%s' when you did not specify ".
          "a start revision, but this behavior does not make much sense in ".
          "most workflows outside of Facebook's historic %s workflow.\n\n".
          "arc no longer assumes '%s'. You must specify a relative commit ".
          "explicitly when you invoke a command (e.g., `%s`, not just `%s`) ".
          "or select a default for this working copy.\n\nIn most cases, the ".
          "best default is '%s'. You can also select '%s' to preserve the ".
          "old behavior, or some other remote or branch. But you almost ".
          "certainly want to select 'origin/master'.\n\n".
          "(Technically: the merge-base of the selected revision and HEAD is ".
          "used to determine the start of the commit range.)",
          'HEAD^',
          'git-svn',
          'HEAD^',
          'arc diff HEAD^',
          'arc diff',
          'origin/master',
          'HEAD^'));

      $prompt = pht('What default do you want to use? [origin/master]');
      $default = phutil_console_prompt($prompt);

      if (!strlen(trim($default))) {
        $default = 'origin/master';
      }

      $default_relative = $default;
      $do_write = true;
    }

    list($object_type) = $this->execxLocal(
      'cat-file -t %s',
      $default_relative);

    if (trim($object_type) !== 'commit') {
      throw new Exception(
        pht(
          "Relative commit '%s' is not the name of a commit!",
          $default_relative));
    }

    if ($do_write) {
      // Don't perform this write until we've verified that the object is a
      // valid commit name.
      $this->writeScratchFile('default-relative-commit', $default_relative);
      $this->setBaseCommitExplanation(
        pht(
          "it is the merge-base of '%s' and HEAD, as you just specified.",
          $default_relative));
    }

    list($merge_base) = $this->execxLocal(
      'merge-base -- %s HEAD',
      $default_relative);

    return trim($merge_base);
  }

  public function getHeadCommit() {
    if ($this->resolvedHeadCommit === null) {
      $this->resolvedHeadCommit = $this->resolveCommit(
        coalesce($this->symbolicHeadCommit, 'HEAD'));
    }

    return $this->resolvedHeadCommit;
  }

  public function setHeadCommit($symbolic_commit) {
    $this->symbolicHeadCommit = $symbolic_commit;
    $this->reloadCommitRange();
    return $this;
  }

  /**
   * Translates a symbolic commit (like "HEAD^") to a commit identifier.
   * @param string_symbol commit.
   * @return string the commit SHA.
   */
  private function resolveCommit($symbolic_commit) {
    list($err, $commit_hash) = $this->execManualLocal(
      'rev-parse %s',
      $symbolic_commit);

    if ($err) {
      throw new ArcanistUsageException(
        pht(
          "Unable to find any git commit named '%s' in this repository.",
          $symbolic_commit));
    }

    return trim($commit_hash);
  }

  private function getDiffFullOptions($detect_moves_and_renames = true) {
    $options = array(
      self::getDiffBaseOptions(),
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U'.$this->getDiffLinesOfContext(),
    );

    if ($detect_moves_and_renames) {
      $options[] = '-M';
      $options[] = '-C';
    }

    return implode(' ', $options);
  }

  private function getDiffBaseOptions() {
    $options = array(
      // Disable external diff drivers, like graphical differs, since Arcanist
      // needs to capture the diff text.
      '--no-ext-diff',
      // Disable textconv so we treat binary files as binary, even if they have
      // an alternative textual representation. TODO: Ideally, Differential
      // would ship up the binaries for 'arc patch' but display the textconv
      // output in the visual diff.
      '--no-textconv',
      // Provide a standard view of submodule changes; the 'log' and 'diff'
      // values do not parse by the diff parser.
      '--submodule=short',
    );
    return implode(' ', $options);
  }

  /**
   * @param the base revision
   * @param head revision. If this is null, the generated diff will include the
   * working copy
   */
  public function getFullGitDiff($base, $head = null) {
    $options = $this->getDiffFullOptions();
    $config_options = array();

    // See T13432. Disable the rare "diff.suppressBlankEmpty" configuration
    // option, which discards the " " (space) change type prefix on unchanged
    // blank lines. At time of writing the parser does not handle these
    // properly, but generating a more-standard diff is generally desirable
    // even if a future parser handles this case more gracefully.

    $config_options[] = '-c';
    $config_options[] = 'diff.suppressBlankEmpty=false';

    if ($head !== null) {
      list($stdout) = $this->execxLocal(
        "%LR diff {$options} %s %s --",
        $config_options,
        $base,
        $head);
    } else {
      list($stdout) = $this->execxLocal(
        "%LR diff {$options} %s --",
        $config_options,
        $base);
    }

    return $stdout;
  }

  /**
   * @param string Path to generate a diff for.
   * @param bool   If true, detect moves and renames. Otherwise, ignore
   *               moves/renames; this is useful because it prompts git to
   *               generate real diff text.
   */
  public function getRawDiffText($path, $detect_moves_and_renames = true) {
    $options = $this->getDiffFullOptions($detect_moves_and_renames);
    list($stdout) = $this->execxLocal(
      "diff {$options} %s -- %s",
      $this->getBaseCommit(),
      $path);
    return $stdout;
  }

  private function getBranchNameFromRef($ref) {
    $count = 0;
    $branch = preg_replace('/^refs\/heads\//', '', $ref, 1, $count);
    if ($count !== 1) {
      return null;
    }

    if (!strlen($branch)) {
      return null;
    }

    return $branch;
  }

  public function getBranchName() {
    list($err, $stdout, $stderr) = $this->execManualLocal(
      'symbolic-ref --quiet HEAD');

    if ($err === 0) {
      // We expect the branch name to come qualified with a refs/heads/ prefix.
      // Verify this, and strip it.
      $ref = rtrim($stdout);
      $branch = $this->getBranchNameFromRef($ref);
      if ($branch === null) {
        throw new Exception(
          pht('Failed to parse %s output!', 'git symbolic-ref'));
      }
      return $branch;
    } else if ($err === 1) {
      // Exit status 1 with --quiet indicates that HEAD is detached.
      return null;
    } else {
      throw new Exception(
        pht('Command %s failed: %s', 'git symbolic-ref', $stderr));
    }
  }

  public function getRemoteURI() {
    // Determine which remote to examine; default to 'origin'
    $remote = 'origin';
    $branch = $this->getBranchName();
    if ($branch) {
      $path = $this->getPathToUpstream($branch);
      if ($path->isConnectedToRemote()) {
        $remote = $path->getRemoteRemoteName();
      }
    }

    return $this->getGitRemoteFetchURI($remote);
  }

  public function getSourceControlPath() {
    // TODO: Try to get something useful here.
    return null;
  }

  public function getGitCommitLog() {
    $relative = $this->getBaseCommit();
    if ($this->repositoryHasNoCommits) {
      // No commits yet.
      return '';
    } else if ($relative == self::GIT_MAGIC_ROOT_COMMIT) {
      // First commit.
      list($stdout) = $this->execxLocal(
        'log --format=medium HEAD --');
    } else {
      // 2..N commits.
      list($stdout) = $this->execxLocal(
        'log --first-parent --format=medium %s --',
        gitsprintf(
          '%s..%s',
          $this->getBaseCommit(),
          $this->getHeadCommit()));
    }
    return $stdout;
  }

  public function getGitHistoryLog() {
    list($stdout) = $this->execxLocal(
      'log --format=medium -n%d %s --',
      self::SEARCH_LENGTH_FOR_PARENT_REVISIONS,
      gitsprintf('%s', $this->getBaseCommit()));
    return $stdout;
  }

  public function getSourceControlBaseRevision() {
    list($stdout) = $this->execxLocal(
      'rev-parse %s',
      $this->getBaseCommit());
    return rtrim($stdout, "\n");
  }

  public function getCanonicalRevisionName($string) {
    $match = null;

    if (preg_match('/@([0-9]+)$/', $string, $match)) {
      $stdout = $this->getHashFromFromSVNRevisionNumber($match[1]);
    } else {
      list($stdout) = $this->execxLocal(
        'show -s --format=%s %s --',
        '%H',
        $string);
    }

    return rtrim($stdout);
  }

  private function executeSVNFindRev($input, $vcs) {
    $match = array();
    list($stdout) = $this->execxLocal(
      'svn find-rev %s',
      $input);
    if (!$stdout) {
      throw new ArcanistUsageException(
        pht(
          'Cannot find the %s equivalent of %s.',
          $vcs,
          $input));
    }
    // When git performs a partial-rebuild during svn
    // look-up, we need to parse the final line
    $lines = explode("\n", $stdout);
    $stdout = $lines[count($lines) - 2];
    return rtrim($stdout);
  }

  // Convert svn revision number to git hash
  public function getHashFromFromSVNRevisionNumber($revision_id) {
    return $this->executeSVNFindRev('r'.$revision_id, 'Git');
  }


  // Convert a git hash to svn revision number
  public function getSVNRevisionNumberFromHash($hash) {
    return $this->executeSVNFindRev($hash, 'SVN');
  }

  private function buildUncommittedStatusViaStatus() {
    $status = $this->buildLocalFuture(
      array(
        'status --porcelain=2 -z',
      ));
    list($stdout) = $status->resolvex();

    $result = new PhutilArrayWithDefaultValue();
    $parts = explode("\0", $stdout);
    while (count($parts) > 1) {
      $entry = array_shift($parts);
      $entry_parts = explode(' ', $entry, 2);
      if ($entry_parts[0] == '1') {
        $entry_parts = explode(' ', $entry, 9);
        $path = $entry_parts[8];
      } else if ($entry_parts[0] == '2') {
        $entry_parts = explode(' ', $entry, 10);
        $path = $entry_parts[9];
      } else if ($entry_parts[0] == 'u') {
        $entry_parts = explode(' ', $entry, 11);
        $path = $entry_parts[10];
      } else if ($entry_parts[0] == '?') {
        $entry_parts = explode(' ', $entry, 2);
        $result[$entry_parts[1]] = self::FLAG_UNTRACKED;
        continue;
      }

      $result[$path] |= self::FLAG_UNCOMMITTED;
      $index_state = substr($entry_parts[1], 0, 1);
      $working_state = substr($entry_parts[1], 1, 1);
      if ($index_state == 'A') {
        $result[$path] |= self::FLAG_ADDED;
      } else if ($index_state == 'M') {
        $result[$path] |= self::FLAG_MODIFIED;
      } else if ($index_state == 'D') {
        $result[$path] |= self::FLAG_DELETED;
      }
      if ($working_state != '.') {
        $result[$path] |= self::FLAG_UNSTAGED;
        if ($index_state == '.') {
          if ($working_state == 'A') {
            $result[$path] |= self::FLAG_ADDED;
          } else if ($working_state == 'M') {
            $result[$path] |= self::FLAG_MODIFIED;
          } else if ($working_state == 'D') {
            $result[$path] |= self::FLAG_DELETED;
          }
        }
      }
      $submodule_tracked = substr($entry_parts[2], 2, 1);
      $submodule_untracked = substr($entry_parts[2], 3, 1);
      if ($submodule_tracked == 'M' || $submodule_untracked == 'U') {
        $result[$path] |= self::FLAG_EXTERNALS;
      }

      if ($entry_parts[0] == '2') {
        $result[array_shift($parts)] = $result[$path] | self::FLAG_DELETED;
        $result[$path] |= self::FLAG_ADDED;
      }
    }
    return $result->toArray();
  }

  protected function buildUncommittedStatus() {
    if (version_compare($this->getGitVersion(), '2.11.0', '>=')) {
      return $this->buildUncommittedStatusViaStatus();
    }

    $diff_options = $this->getDiffBaseOptions();

    if ($this->repositoryHasNoCommits) {
      $diff_base = self::GIT_MAGIC_ROOT_COMMIT;
    } else {
      $diff_base = 'HEAD';
    }

    // Find uncommitted changes.
    $uncommitted_future = $this->buildLocalFuture(
      array(
        'diff %C --raw %s --',
        $diff_options,
        gitsprintf('%s', $diff_base),
      ));

    $untracked_future = $this->buildLocalFuture(
      array(
        'ls-files --others --exclude-standard',
      ));

    // Unstaged changes
    $unstaged_future = $this->buildLocalFuture(
      array(
        'diff-files --name-only',
      ));

    $futures = array(
      $uncommitted_future,
      $untracked_future,
      // NOTE: `git diff-files` races with each of these other commands
      // internally, and resolves with inconsistent results if executed
      // in parallel. To work around this, DO NOT run it at the same time.
      // After the other commands exit, we can start the `diff-files` command.
    );

    id(new FutureIterator($futures))->resolveAll();

    // We're clear to start the `git diff-files` now.
    $unstaged_future->start();

    $result = new PhutilArrayWithDefaultValue();

    list($stdout) = $uncommitted_future->resolvex();
    $uncommitted_files = $this->parseGitRawDiff($stdout);
    foreach ($uncommitted_files as $path => $mask) {
      $result[$path] |= ($mask | self::FLAG_UNCOMMITTED);
    }

    list($stdout) = $untracked_future->resolvex();
    $stdout = rtrim($stdout, "\n");
    if (strlen($stdout)) {
      $stdout = explode("\n", $stdout);
      foreach ($stdout as $path) {
        $result[$path] |= self::FLAG_UNTRACKED;
      }
    }

    list($stdout, $stderr) = $unstaged_future->resolvex();
    $stdout = rtrim($stdout, "\n");
    if (strlen($stdout)) {
      $stdout = explode("\n", $stdout);
      foreach ($stdout as $path) {
        $result[$path] |= self::FLAG_UNSTAGED;
      }
    }

    return $result->toArray();
  }

  protected function buildCommitRangeStatus() {
    list($stdout, $stderr) = $this->execxLocal(
      'diff %C --raw %s HEAD --',
      $this->getDiffBaseOptions(),
      gitsprintf('%s', $this->getBaseCommit()));

    return $this->parseGitRawDiff($stdout);
  }

  public function getGitConfig($key, $default = null) {
    list($err, $stdout) = $this->execManualLocal('config %s', $key);
    if ($err) {
      return $default;
    }
    return rtrim($stdout);
  }

  public function getAuthor() {
    list($stdout) = $this->execxLocal('var GIT_AUTHOR_IDENT');
    return preg_replace('/\s+<.*/', '', rtrim($stdout, "\n"));
  }

  public function addToCommit(array $paths) {
    $this->execxLocal(
      'add -A -- %Ls',
      $paths);
    $this->reloadWorkingCopy();
    return $this;
  }

  public function doCommit($message) {
    $tmp_file = new TempFile();
    Filesystem::writeFile($tmp_file, $message);

    // NOTE: "--allow-empty-message" was introduced some time after 1.7.0.4,
    // so we do not provide it and thus require a message.

    $this->execxLocal(
      'commit -F %s',
      $tmp_file);

    $this->reloadWorkingCopy();

    return $this;
  }

  public function amendCommit($message = null) {
    if ($message === null) {
      $this->execxLocal('commit --amend --allow-empty -C HEAD');
    } else {
      $tmp_file = new TempFile();
      Filesystem::writeFile($tmp_file, $message);
      $this->execxLocal(
        'commit --amend --allow-empty -F %s',
        $tmp_file);
    }

    $this->reloadWorkingCopy();
    return $this;
  }

  private function parseGitRawDiff($status, $full = false) {
    static $flags = array(
      'A' => self::FLAG_ADDED,
      'M' => self::FLAG_MODIFIED,
      'D' => self::FLAG_DELETED,
    );

    $status = trim($status);
    $lines = array();
    foreach (explode("\n", $status) as $line) {
      if ($line) {
        $lines[] = preg_split("/[ \t]/", $line, 6);
      }
    }

    $files = array();
    foreach ($lines as $line) {
      $mask = 0;

      // "git diff --raw" lines begin with a ":" character.
      $old_mode = ltrim($line[0], ':');
      $new_mode = $line[1];

      // The hashes may be padded with "." characters for alignment. Discard
      // them.
      $old_hash = rtrim($line[2], '.');
      $new_hash = rtrim($line[3], '.');

      $flag = $line[4];
      $file = $line[5];

      $new_value = intval($new_mode, 8);
      $is_submodule = (($new_value & 0160000) === 0160000);

      if (($is_submodule) &&
          ($flag == 'M') &&
          ($old_hash === $new_hash) &&
          ($old_mode === $new_mode)) {
        // See T9455. We see this submodule as "modified", but the old and new
        // hashes are the same and the old and new modes are the same, so we
        // don't directly see a modification.

        // We can end up here if we have a submodule which has uncommitted
        // changes inside of it (for example, the user has added untracked
        // files or made uncommitted changes to files in the submodule). In
        // this case, we set a different flag because we can't meaningfully
        // give users the same prompt.

        // Note that if the submodule has real changes from the parent
        // perspective (the base commit has changed) and also has uncommitted
        // changes, we'll only see the real changes and miss the uncommitted
        // changes. At the time of writing, there is no reasonable porcelain
        // for finding those changes, and the impact of this error seems small.

        $mask |= self::FLAG_EXTERNALS;
      } else if (isset($flags[$flag])) {
        $mask |= $flags[$flag];
      } else if ($flag[0] == 'R') {
        $both = explode("\t", $file);
        if ($full) {
          $files[$both[0]] = array(
            'mask' => $mask | self::FLAG_DELETED,
            'ref'  => str_repeat('0', 40),
          );
        } else {
          $files[$both[0]] = $mask | self::FLAG_DELETED;
        }
        $file = $both[1];
        $mask |= self::FLAG_ADDED;
      } else if ($flag[0] == 'C') {
        $both = explode("\t", $file);
        $file = $both[1];
        $mask |= self::FLAG_ADDED;
      }

      if ($full) {
        $files[$file] = array(
          'mask' => $mask,
          'ref'  => $new_hash,
        );
      } else {
        $files[$file] = $mask;
      }
    }

    return $files;
  }

  public function getAllFiles() {
    $future = $this->buildLocalFuture(array('ls-files -z'));
    return id(new LinesOfALargeExecFuture($future))
      ->setDelimiter("\0");
  }

  public function getChangedFiles($since_commit) {
    list($stdout) = $this->execxLocal(
      'diff --raw %s --',
      gitsprintf('%s', $since_commit));
    return $this->parseGitRawDiff($stdout);
  }

  public function getBlame($path) {
    list($stdout) = $this->execxLocal(
      'blame --porcelain -w -M %s -- %s',
      gitsprintf('%s', $this->getBaseCommit()),
      $path);

    // the --porcelain format prints at least one header line per source line,
    // then the source line prefixed by a tab character
    $blame_info = preg_split('/^\t.*\n/m', rtrim($stdout));

    // commit info is not repeated in these headers, so cache it
    $revision_data = array();

    $blame = array();
    foreach ($blame_info as $line_info) {
      $revision = substr($line_info, 0, 40);
      $data = idx($revision_data, $revision, array());

      if (empty($data)) {
        $matches = array();
        if (!preg_match('/^author (.*)$/m', $line_info, $matches)) {
          throw new Exception(
            pht(
              'Unexpected output from %s: no author for commit %s',
              'git blame',
              $revision));
        }
        $data['author'] = $matches[1];
        $data['from_first_commit'] = preg_match('/^boundary$/m', $line_info);
        $revision_data[$revision] = $data;
      }

      // Ignore lines predating the git repository (on a boundary commit)
      // rather than blaming them on the oldest diff's unfortunate author
      if (!$data['from_first_commit']) {
        $blame[] = array($data['author'], $revision);
      }
    }

    return $blame;
  }

  public function getOriginalFileData($path) {
    return $this->getFileDataAtRevision($path, $this->getBaseCommit());
  }

  public function getCurrentFileData($path) {
    return $this->getFileDataAtRevision($path, 'HEAD');
  }

  private function parseGitTree($stdout) {
    $result = array();

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      return $result;
    }

    $lines = explode("\n", $stdout);
    foreach ($lines as $line) {
      $matches = array();
      $ok = preg_match(
        '/^(\d{6}) (blob|tree|commit) ([a-z0-9]{40})[\t](.*)$/',
        $line,
        $matches);
      if (!$ok) {
        throw new Exception(pht('Failed to parse %s output!', 'git ls-tree'));
      }
      $result[$matches[4]] = array(
        'mode' => $matches[1],
        'type' => $matches[2],
        'ref'  => $matches[3],
      );
    }
    return $result;
  }

  private function getFileDataAtRevision($path, $revision) {
    // NOTE: We don't want to just "git show {$revision}:{$path}" since if the
    // path was a directory at the given revision we'll get a list of its files
    // and treat it as though it as a file containing a list of other files,
    // which is silly.

    if (!strlen($path)) {
      // No filename, so there's no content (Probably new/deleted file).
      return null;
    }

    list($stdout) = $this->execxLocal(
      'ls-tree %s -- %s',
      gitsprintf('%s', $revision),
      $path);

    $info = $this->parseGitTree($stdout);
    if (empty($info[$path])) {
      // No such path, or the path is a directory and we executed 'ls-tree dir/'
      // and got a list of its contents back.
      return null;
    }

    if ($info[$path]['type'] != 'blob') {
      // Path is or was a directory, not a file.
      return null;
    }

    list($stdout) = $this->execxLocal(
      'cat-file blob -- %s',
       $info[$path]['ref']);
    return $stdout;
  }

  /**
   * Returns names of all the branches in the current repository.
   *
   * @return list<dict<string, string>> Dictionary of branch information.
   */
  private function getAllBranches() {
    $field_list = array(
      '%(refname)',
      '%(objectname)',
      '%(committerdate:raw)',
      '%(tree)',
      '%(subject)',
      '%(subject)%0a%0a%(body)',
      '%02',
    );

    list($stdout) = $this->execxLocal(
      'for-each-ref --format=%s -- refs/heads',
      implode('%01', $field_list));

    $current = $this->getBranchName();
    $result = array();

    $lines = explode("\2", $stdout);
    foreach ($lines as $line) {
      $line = trim($line);
      if (!strlen($line)) {
        continue;
      }

      $fields = explode("\1", $line, 6);
      list($ref, $hash, $epoch, $tree, $desc, $text) = $fields;

      $branch = $this->getBranchNameFromRef($ref);
      if ($branch !== null) {
        $result[] = array(
          'current' => ($branch === $current),
          'name' => $branch,
          'ref' => $ref,
          'hash' => $hash,
          'tree' => $tree,
          'epoch' => (int)$epoch,
          'desc' => $desc,
          'text' => $text,
        );
      }
    }

    return $result;
  }

  public function getBaseCommitRef() {
    $base_commit = $this->getBaseCommit();

    if ($base_commit === self::GIT_MAGIC_ROOT_COMMIT) {
      return null;
    }

    $base_message = $this->getCommitMessage($base_commit);

    // TODO: We should also pull the tree hash.

    return $this->newCommitRef()
      ->setCommitHash($base_commit)
      ->attachMessage($base_message);
  }

  public function getWorkingCopyRevision() {
    list($stdout) = $this->execxLocal('rev-parse HEAD');
    return rtrim($stdout, "\n");
  }

  public function isHistoryDefaultImmutable() {
    return false;
  }

  public function supportsAmend() {
    return true;
  }

  public function supportsCommitRanges() {
    return true;
  }

  public function supportsLocalCommits() {
    return true;
  }

  public function hasLocalCommit($commit) {
    try {
      if (!$this->getCanonicalRevisionName($commit)) {
        return false;
      }
    } catch (CommandException $exception) {
      return false;
    }
    return true;
  }

  public function getAllLocalChanges() {
    $diff = $this->getFullGitDiff($this->getBaseCommit());
    if (!strlen(trim($diff))) {
      return array();
    }
    $parser = new ArcanistDiffParser();
    return $parser->parseDiff($diff);
  }

  public function getFinalizedRevisionMessage() {
    return pht(
      "You may now push this commit upstream, as appropriate (e.g. with ".
      "'%s', or '%s', or by printing and faxing it).",
      'git push',
      'git svn dcommit');
  }

  public function getCommitMessage($commit) {
    list($message) = $this->execxLocal(
      'log -n1 --format=%C %s --',
      '%s%n%n%b',
      gitsprintf('%s', $commit));
    return $message;
  }

  public function loadWorkingCopyDifferentialRevisions(
    ConduitClient $conduit,
    array $query) {

    $messages = $this->getGitCommitLog();
    if (!strlen($messages)) {
      return array();
    }

    $parser = new ArcanistDiffParser();
    $messages = $parser->parseDiff($messages);

    // First, try to find revisions by explicit revision IDs in commit messages.
    $reason_map = array();
    $revision_ids = array();
    foreach ($messages as $message) {
      $object = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $message->getMetadata('message'));
      if ($object->getRevisionID()) {
        $revision_ids[] = $object->getRevisionID();
        $reason_map[$object->getRevisionID()] = $message->getCommitHash();
      }
    }

    if ($revision_ids) {
      $results = $conduit->callMethodSynchronous(
        'differential.query',
        $query + array(
          'ids' => $revision_ids,
        ));

      foreach ($results as $key => $result) {
        $hash = substr($reason_map[$result['id']], 0, 16);
        $results[$key]['why'] = pht(
          "Commit message for '%s' has explicit 'Differential Revision'.",
          $hash);
      }

      return $results;
    }

    // If we didn't succeed, try to find revisions by hash.
    $hashes = array();
    foreach ($this->getLocalCommitInformation() as $commit) {
      $hashes[] = array('gtcm', $commit['commit']);
      $hashes[] = array('gttr', $commit['tree']);
    }

    $results = $conduit->callMethodSynchronous(
      'differential.query',
      $query + array(
        'commitHashes' => $hashes,
      ));

    foreach ($results as $key => $result) {
      $results[$key]['why'] = pht(
        'A git commit or tree hash in the commit range is already attached '.
        'to the Differential revision.');
    }

    return $results;
  }

  public function updateWorkingCopy() {
    $this->execxLocal('pull');
    $this->reloadWorkingCopy();
  }

  public function getCommitSummary($commit) {
    if ($commit == self::GIT_MAGIC_ROOT_COMMIT) {
      return pht('(The Empty Tree)');
    }

    list($summary) = $this->execxLocal(
      'log -n 1 %s %s --',
      '--format=%s',
      gitsprintf('%s', $commit));

    return trim($summary);
  }

  public function isGitSubversionRepo() {
    return Filesystem::pathExists($this->getPath('.git/svn'));
  }

  public function resolveBaseCommitRule($rule, $source) {
    list($type, $name) = explode(':', $rule, 2);

    switch ($type) {
      case 'git':
        $matches = null;
        if (preg_match('/^merge-base\((.+)\)$/', $name, $matches)) {
          list($err, $merge_base) = $this->execManualLocal(
            'merge-base -- %s HEAD',
            $matches[1]);
          if (!$err) {
            $this->setBaseCommitExplanation(
              pht(
                "it is the merge-base of '%s' and HEAD, as specified by ".
                "'%s' in your %s 'base' configuration.",
                $matches[1],
                $rule,
                $source));
            return trim($merge_base);
          }
        } else if (preg_match('/^branch-unique\((.+)\)$/', $name, $matches)) {
          list($err, $merge_base) = $this->execManualLocal(
            'merge-base -- %s HEAD',
            $matches[1]);
          if ($err) {
            return null;
          }
          $merge_base = trim($merge_base);

          list($commits) = $this->execxLocal(
            'log --format=%C %s..HEAD --',
            '%H',
            $merge_base);
          $commits = array_filter(explode("\n", $commits));

          if (!$commits) {
            return null;
          }

          $commits[] = $merge_base;

          $head_branch_count = null;
          $all_branch_names = ipull($this->getAllBranches(), 'name');
          foreach ($commits as $commit) {
            // Ideally, we would use something like "for-each-ref --contains"
            // to get a filtered list of branches ready for script consumption.
            // Instead, try to get predictable output from "branch --contains".

            $flags = array();
            $flags[] = '--no-color';

            // NOTE: The "--no-column" flag was introduced in Git 1.7.11, so
            // don't pass it if we're running an older version. See T9953.
            $version = $this->getGitVersion();
            if (version_compare($version, '1.7.11', '>=')) {
              $flags[] = '--no-column';
            }

            list($branches) = $this->execxLocal(
              'branch %Ls --contains %s',
              $flags,
              $commit);
            $branches = array_filter(explode("\n", $branches));

            // Filter the list, removing the "current" marker (*) and ignoring
            // anything other than known branch names (mainly, any possible
            // "detached HEAD" or "no branch" line).
            foreach ($branches as $key => $branch) {
              $branch = trim($branch, ' *');
              if (in_array($branch, $all_branch_names)) {
                $branches[$key] = $branch;
              } else {
                unset($branches[$key]);
              }
            }

            if ($head_branch_count === null) {
              // If this is the first commit, it's HEAD. Count how many
              // branches it is on; we want to include commits on the same
              // number of branches. This covers a case where this branch
              // has sub-branches and we're running "arc diff" here again
              // for whatever reason.
              $head_branch_count = count($branches);
            } else if (count($branches) > $head_branch_count) {
              $branches = implode(', ', $branches);
              $this->setBaseCommitExplanation(
                pht(
                  "it is the first commit between '%s' (the merge-base of ".
                  "'%s' and HEAD) which is also contained by another branch ".
                  "(%s).",
                  $merge_base,
                  $matches[1],
                  $branches));
              return $commit;
            }
          }
        } else {
          list($err) = $this->execManualLocal(
            'cat-file -t %s',
            $name);
          if (!$err) {
            $this->setBaseCommitExplanation(
              pht(
                "it is specified by '%s' in your %s 'base' configuration.",
                $rule,
                $source));
            return $name;
          }
        }
        break;
      case 'arc':
        switch ($name) {
          case 'empty':
            $this->setBaseCommitExplanation(
              pht(
                "you specified '%s' in your %s 'base' configuration.",
                $rule,
                $source));
            return self::GIT_MAGIC_ROOT_COMMIT;
          case 'amended':
            $text = $this->getCommitMessage('HEAD');
            $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
              $text);
            if ($message->getRevisionID()) {
              $this->setBaseCommitExplanation(
                pht(
                  "HEAD has been amended with 'Differential Revision:', ".
                  "as specified by '%s' in your %s 'base' configuration.",
                  $rule,
                  $source));
              return 'HEAD^';
            }
            break;
          case 'upstream':
            list($err, $upstream) = $this->execManualLocal(
              'rev-parse --abbrev-ref --symbolic-full-name %s',
              '@{upstream}');
            if (!$err) {
              $upstream = rtrim($upstream);
              list($upstream_merge_base) = $this->execxLocal(
                'merge-base -- %s HEAD',
                $upstream);
              $upstream_merge_base = rtrim($upstream_merge_base);
              $this->setBaseCommitExplanation(
                pht(
                  "it is the merge-base of the upstream of the current branch ".
                  "and HEAD, and matched the rule '%s' in your %s ".
                  "'base' configuration.",
                  $rule,
                  $source));
              return $upstream_merge_base;
            }
            break;
          case 'this':
            $this->setBaseCommitExplanation(
              pht(
                "you specified '%s' in your %s 'base' configuration.",
                $rule,
                $source));
            return 'HEAD^';
        }
      default:
        return null;
    }

    return null;
  }

  public function canStashChanges() {
    return true;
  }

  public function stashChanges() {
    $this->execxLocal('stash');
    $this->reloadWorkingCopy();
  }

  public function unstashChanges() {
    $this->execxLocal('stash pop');
  }

  protected function didReloadCommitRange() {
    // After an amend, the symbolic head may resolve to a different commit.
    $this->resolvedHeadCommit = null;
  }

  /**
   * Follow the chain of tracking branches upstream until we reach a remote
   * or cycle locally.
   *
   * @param string Ref to start from.
   * @return ArcanistGitUpstreamPath Path to an upstream.
   */
  public function getPathToUpstream($start) {
    $cursor = $start;
    $path = new ArcanistGitUpstreamPath();
    while (true) {
      list($err, $upstream) = $this->execManualLocal(
        'rev-parse --symbolic-full-name %s@{upstream}',
        $cursor);

      if ($err) {
        // We ended up somewhere with no tracking branch, so we're done.
        break;
      }

      $upstream = trim($upstream);

      if (preg_match('(^refs/heads/)', $upstream)) {
        $upstream = preg_replace('(^refs/heads/)', '', $upstream);

        $is_cycle = $path->getUpstream($upstream);

        $path->addUpstream(
          $cursor,
          array(
            'type' => ArcanistGitUpstreamPath::TYPE_LOCAL,
            'name' => $upstream,
            'cycle' => $is_cycle,
          ));

        if ($is_cycle) {
          // We ran into a local cycle, so we're done.
          break;
        }

        // We found another local branch, so follow that one upriver.
        $cursor = $upstream;
        continue;
      }

      if (preg_match('(^refs/remotes/)', $upstream)) {
        $upstream = preg_replace('(^refs/remotes/)', '', $upstream);
        list($remote, $branch) = explode('/', $upstream, 2);

        $path->addUpstream(
          $cursor,
          array(
            'type' => ArcanistGitUpstreamPath::TYPE_REMOTE,
            'name' => $branch,
            'remote' => $remote,
          ));

        // We found a remote, so we're done.
        break;
      }

      throw new Exception(
        pht(
          'Got unrecognized upstream format ("%s") from Git, expected '.
          '"refs/heads/..." or "refs/remotes/...".',
          $upstream));
    }

    return $path;
  }

  public function isPerforceRemote($remote_name) {
    // See T13434. In Perforce workflows, "git p4 clone" creates "p4" refs
    // under "refs/remotes/", but does not define a real remote named "p4".

    // We treat this remote as though it were a real remote during "arc land",
    // but it does not respond to commands like "git remote show p4", so we
    // need to handle it specially.

    if ($remote_name !== 'p4') {
      return false;
    }

    $remote_dir = $this->getMetadataPath().'/refs/remotes/p4';
    if (!Filesystem::pathExists($remote_dir)) {
      return false;
    }

    return true;
  }

  public function isPushableRemote($remote_name) {
    $uri = $this->getGitRemotePushURI($remote_name);
    return ($uri !== null);
  }

  public function isFetchableRemote($remote_name) {
    $uri = $this->getGitRemoteFetchURI($remote_name);
    return ($uri !== null);
  }

  private function getGitRemoteFetchURI($remote_name) {
    return $this->getGitRemoteURI($remote_name, $for_push = false);
  }

  private function getGitRemotePushURI($remote_name) {
    return $this->getGitRemoteURI($remote_name, $for_push = true);
  }

  private function getGitRemoteURI($remote_name, $for_push) {
    $remote_uri = $this->loadGitRemoteURI($remote_name, $for_push);

    if ($remote_uri !== null) {
      $remote_uri = rtrim($remote_uri);
      if (!strlen($remote_uri)) {
        $remote_uri = null;
      }
    }

    return $remote_uri;
  }

  private function loadGitRemoteURI($remote_name, $for_push) {
    // Try to identify the best URI for a given remote. This is complicated
    // because remotes may have different "push" and "fetch" URIs, may
    // rewrite URIs with "insteadOf" configuration, and different versions
    // of Git support different URI resolution commands.

    // Remotes may also have more than one URI of a given type, but we ignore
    // those cases here.

    // Start with "git remote get-url [--push]". This is the simplest and
    // most accurate command, but was introduced most recently in Git's
    // history.

    $argv = array();
    if ($for_push) {
      $argv[] = '--push';
    }

    list($err, $stdout) = $this->execManualLocal(
      'remote get-url %Ls -- %s',
      $argv,
      $remote_name);
    if (!$err) {
      return $stdout;
    }

    // See T13481. If "git remote get-url [--push]" failed, it might be because
    // the remote does not exist, but it might also be because the version of
    // Git is too old to support "git remote get-url", which was introduced
    // in Git 2.7 (circa late 2015).

    $git_version = $this->getGitVersion();
    if (version_compare($git_version, '2.7', '>=')) {
      // This version of Git should support "git remote get-url --push", but
      // the command failed, so conclude this is not a valid remote and thus
      // there is no remote URI.
      return null;
    }

    // If we arrive here, we're in a version of Git which is too old to
    // support "git remote get-url [--push]". We're going to fall back to
    // older and less accurate mechanisms for figuring out the remote URI.

    // The first mechanism we try is "git ls-remote --get-url". This exists
    // in Git 1.7.5 or newer. It only gives us the fetch URI, so this result
    // will be incorrect if a remote has different fetch and push URIs.
    // However, this is very rare, and this result is almost always correct.

    // Note that some old versions of Git do not parse "--" in this command
    // properly. We omit it since it doesn't seem like there's anything
    // dangerous an attacker can do even if they can choose a remote name to
    // intentionally cause an argument misparse.

    // This will cause the command to behave incorrectly for remotes with
    // names which are also valid flags, like "--quiet".

    list($err, $stdout) = $this->execManualLocal(
      'ls-remote --get-url %s',
      $remote_name);
    if (!$err) {
      // The "git ls-remote --get-url" command just echoes the remote name
      // (like "origin") if no remote URI is found. Treat this like a failure.
      $output_is_input = (rtrim($stdout) === $remote_name);
      if (!$output_is_input) {
        return $stdout;
      }
    }

    if (version_compare($git_version, '1.7.5', '>=')) {
      // This version of Git should support "git ls-remote --get-url", but
      // the command failed (or echoed the input), so conclude the remote
      // really does not exist.
      return null;
    }

    // Fall back to the very old "git config -- remote.origin.url" command.
    // This does not give us push URLs and does not resolve "insteadOf"
    // aliases, but still works in the simplest (and most common) cases.

    list($err, $stdout) = $this->execManualLocal(
      'config -- %s',
      sprintf('remote.%s.url', $remote_name));
    if (!$err) {
      return $stdout;
    }

    return null;
  }

  protected function newCurrentCommitSymbol() {
    return 'HEAD';
  }

  public function isGitLFSWorkingCopy() {

    // We're going to run:
    //
    //   $ git ls-files -z -- ':(attr:filter=lfs)'
    //
    // ...and exit as soon as it generates any field terminated with a "\0".
    //
    // If this command generates any such output, that means this working copy
    // contains at least one LFS file, so it's an LFS working copy. If it
    // exits with no error and no output, this is not an LFS working copy.
    //
    // If it exits with an error, we're in trouble.

    $future = $this->buildLocalFuture(
      array(
        'ls-files -z -- %s',
        ':(attr:filter=lfs)',
      ));

    $lfs_list = id(new LinesOfALargeExecFuture($future))
      ->setDelimiter("\0");

    try {
      foreach ($lfs_list as $lfs_file) {
        // We have our answer, so we can throw the subprocess away.
        $future->resolveKill();
        return true;
      }
      return false;
    } catch (CommandException $ex) {
      // This is probably an older version of Git. Continue below.
    }

    // In older versions of Git, the first command will fail with an error
    // ("Invalid pathspec magic..."). See PHI1718.
    //
    // Some other tests we could use include:
    //
    // (1) Look for ".gitattributes" at the repository root. This approach is
    // a rough approximation because ".gitattributes" may be global or in a
    // subdirectory. See D21190.
    //
    // (2) Use "git check-attr" and pipe a bunch of files into it, roughly
    // like this:
    //
    //   $ git ls-files -z -- | git check-attr --stdin -z filter --
    //
    // However, the best version of this check I could come up with is fairly
    // slow in even moderately large repositories (~200ms in a repository with
    // 10K paths). See D21190.
    //
    // (3) Use "git lfs ls-files". This is even worse than piping "ls-files"
    // to "check-attr" in PHP (~600ms in a repository with 10K paths).
    //
    // (4) Give up and just assume the repository isn't LFS. This is the
    // current behavior.

    return false;
  }

  protected function newLandEngine() {
    return new ArcanistGitLandEngine();
  }

  protected function newWorkEngine() {
    return new ArcanistGitWorkEngine();
  }

  public function newLocalState() {
    return id(new ArcanistGitLocalState())
      ->setRepositoryAPI($this);
  }

  public function readRawCommit($hash) {
    list($stdout) = $this->execxLocal(
      'cat-file commit -- %s',
      $hash);

    return ArcanistGitRawCommit::newFromRawBlob($stdout);
  }

  public function writeRawCommit(ArcanistGitRawCommit $commit) {
    $blob = $commit->getRawBlob();

    $future = $this->execFutureLocal('hash-object -t commit --stdin -w');
    $future->write($blob);
    list($stdout) = $future->resolvex();

    return trim($stdout);
  }

  protected function newSupportedMarkerTypes() {
    return array(
      ArcanistMarkerRef::TYPE_BRANCH,
    );
  }

  protected function newMarkerRefQueryTemplate() {
    return new ArcanistGitRepositoryMarkerQuery();
  }

  protected function newRemoteRefQueryTemplate() {
    return new ArcanistGitRepositoryRemoteQuery();
  }

  protected function newNormalizedURI($uri) {
    return new ArcanistRepositoryURINormalizer(
      ArcanistRepositoryURINormalizer::TYPE_GIT,
      $uri);
  }

  protected function newPublishedCommitHashes() {
    $remotes = $this->newRemoteRefQuery()
      ->execute();
    if (!$remotes) {
      return array();
    }

    $markers = $this->newMarkerRefQuery()
      ->withIsRemoteCache(true)
      ->execute();

    if (!$markers) {
      return array();
    }

    $runtime = $this->getRuntime();
    $workflow = $runtime->getCurrentWorkflow();

    $workflow->loadHardpoints(
      $remotes,
      ArcanistRemoteRef::HARDPOINT_REPOSITORYREFS);

    $remotes = mpull($remotes, null, 'getRemoteName');

    $hashes = array();

    foreach ($markers as $marker) {
      $remote_name = $marker->getRemoteName();
      $remote = idx($remotes, $remote_name);
      if (!$remote) {
        continue;
      }

      if (!$remote->isPermanentRef($marker)) {
        continue;
      }

      $hashes[] = $marker->getCommitHash();
    }

    return $hashes;
  }

  protected function newCommitGraphQueryTemplate() {
    return new ArcanistGitCommitGraphQuery();
  }

}
