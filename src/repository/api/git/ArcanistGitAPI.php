<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Interfaces with Git working copies.
 *
 * @group workingcopy
 */
final class ArcanistGitAPI extends ArcanistRepositoryAPI {

  private $status;
  private $relativeCommit = null;
  private $repositoryHasNoCommits = false;
  const SEARCH_LENGTH_FOR_PARENT_REVISIONS = 16;

  /**
   * For the repository's initial commit, 'git diff HEAD^' and similar do
   * not work. Using this instead does work.
   */
  const GIT_MAGIC_ROOT_COMMIT = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

  public static function newHookAPI($root) {
    return new ArcanistGitAPI($root);
  }

  protected function buildLocalFuture(array $argv) {

    $argv[0] = 'git '.$argv[0];

    $future = newv('ExecFuture', $argv);
    $future->setCWD($this->getPath());
    return $future;
  }


  public function getSourceControlSystemName() {
    return 'git';
  }

  public function getHasCommits() {
    return !$this->repositoryHasNoCommits;
  }

  public function setRelativeCommit($relative_commit) {
    $this->relativeCommit = $relative_commit;
    return $this;
  }

  public function getLocalCommitInformation() {
    if ($this->repositoryHasNoCommits) {
      // Zero commits.
      throw new Exception(
        "You can't get local commit information for a repository with no ".
        "commits.");
    } else if ($this->relativeCommit == self::GIT_MAGIC_ROOT_COMMIT) {
      // One commit.
      $against = 'HEAD';
    } else {
      // 2..N commits.
      $against = $this->getRelativeCommit().'..HEAD';
    }

    // NOTE: Windows escaping of "%" symbols apparently is inherently broken;
    // when passed throuhgh escapeshellarg() they are replaced with spaces.

    // TODO: Learn how cmd.exe works and find some clever workaround?

    // NOTE: If we use "%x00", output is truncated in Windows.

    list($info) = $this->execxLocal(
      phutil_is_windows()
        ? 'log %s --format=%C --'
        : 'log %s --format=%s --',
      $against,
      '%H%x01%T%x01%P%x01%at%x01%an%x01%s');

    $commits = array();

    $info = trim($info);
    $info = explode("\n", $info);
    foreach ($info as $line) {
      list($commit, $tree, $parents, $time, $author, $title)
        = explode("\1", $line, 6);

      $commits[] = array(
        'commit'  => $commit,
        'tree'    => $tree,
        'parents' => array_filter(explode(' ', $parents)),
        'time'    => $time,
        'author'  => $author,
        'summary' => $title,
      );
    }

    return $commits;
  }

  public function getRelativeCommit() {
    if ($this->relativeCommit === null) {

      // Detect zero-commit or one-commit repositories. There is only one
      // relative-commit value that makes any sense in these repositories: the
      // empty tree.
      list($err) = $this->execManualLocal('rev-parse --verify HEAD^');
      if ($err) {
        list($err) = $this->execManualLocal('rev-parse --verify HEAD');
        if ($err) {
          $this->repositoryHasNoCommits = true;
        }

        $this->relativeCommit = self::GIT_MAGIC_ROOT_COMMIT;

        return $this->relativeCommit;
      }

      $default_relative = $this->readScratchFile('default-relative-commit');
      $do_write = false;
      if (!$default_relative) {

        // TODO: Remove the history lesson soon.

        echo phutil_console_format(
          "<bg:green>** Select a Default Commit Range **</bg>\n\n");
        echo phutil_console_wrap(
          "You're running a command which operates on a range of revisions ".
          "(usually, from some revision to HEAD) but have not specified the ".
          "revision that should determine the start of the range.\n\n".
          "Previously, arc assumed you meant 'HEAD^' when you did not specify ".
          "a start revision, but this behavior does not make much sense in ".
          "most workflows outside of Facebook's historic git-svn workflow.\n\n".
          "arc no longer assumes 'HEAD^'. You must specify a relative commit ".
          "explicitly when you invoke a command (e.g., `arc diff HEAD^`, not ".
          "just `arc diff`) or select a default for this working copy.\n\n".
          "In most cases, the best default is 'origin/master'. You can also ".
          "select 'HEAD^' to preserve the old behavior, or some other remote ".
          "or branch. But you almost certainly want to select ".
          "'origin/master'.\n\n".
          "(Technically: the merge-base of the selected revision and HEAD is ".
          "used to determine the start of the commit range.)");

        $prompt = "What default do you want to use? [origin/master]";
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
          "Relative commit '{$relative}' is not the name of a commit!");
      }

      if ($do_write) {
        // Don't perform this write until we've verified that the object is a
        // valid commit name.
        $this->writeScratchFile('default-relative-commit', $default_relative);
      }

      list($merge_base) = $this->execxLocal(
        'merge-base %s HEAD',
        $default_relative);

      $this->relativeCommit = trim($merge_base);
    }

    return $this->relativeCommit;
  }

  private function getDiffFullOptions() {
    $options = array(
      self::getDiffBaseOptions(),
      '-M',
      '-C',
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U'.$this->getDiffLinesOfContext(),
    );
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
    );
    return implode(' ', $options);
  }

  public function getFullGitDiff() {
    $options = $this->getDiffFullOptions();
    list($stdout) = $this->execxLocal(
      "diff {$options} %s --",
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getRawDiffText($path) {
    $options = $this->getDiffFullOptions();
    list($stdout) = $this->execxLocal(
      "diff {$options} %s -- %s",
      $this->getRelativeCommit(),
      $path);
    return $stdout;
  }

  public function getBranchName() {
    // TODO: consider:
    //
    //    $ git rev-parse --abbrev-ref `git symbolic-ref HEAD`
    //
    // But that may fail if you're not on a branch.
    list($stdout) = $this->execxLocal('branch');

    $matches = null;
    if (preg_match('/^\* (.+)$/m', $stdout, $matches)) {
      return $matches[1];
    }
    return null;
  }

  public function getSourceControlPath() {
    // TODO: Try to get something useful here.
    return null;
  }

  public function getGitCommitLog() {
    $relative = $this->getRelativeCommit();
    if ($this->repositoryHasNoCommits) {
      // No commits yet.
      return '';
    } else if ($relative == self::GIT_MAGIC_ROOT_COMMIT) {
      // First commit.
      list($stdout) = $this->execxLocal('log --format=medium HEAD');
    } else {
      // 2..N commits.
      list($stdout) = $this->execxLocal(
        'log --first-parent --format=medium %s..HEAD',
        $this->getRelativeCommit());
    }
    return $stdout;
  }

  public function getGitHistoryLog() {
    list($stdout) = $this->execxLocal(
      'log --format=medium -n%d %s',
      self::SEARCH_LENGTH_FOR_PARENT_REVISIONS,
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getSourceControlBaseRevision() {
    list($stdout) = $this->execxLocal(
      'rev-parse %s',
      $this->getRelativeCommit());
    return rtrim($stdout, "\n");
  }

  public function getCanonicalRevisionName($string) {
    list($stdout) = $this->execxLocal('show -s --format=%C %s',
      '%H', $string);
    return rtrim($stdout);
  }

  public function getWorkingCopyStatus() {
    if (!isset($this->status)) {

      $options = $this->getDiffBaseOptions();

      // -- parallelize these slow cpu bound git calls.

      // Find committed changes.
      $committed_future = $this->buildLocalFuture(
        array(
          "diff {$options} --raw %s --",
          $this->getRelativeCommit(),
        ));

      // Find uncommitted changes.
      $uncommitted_future = $this->buildLocalFuture(
        array(
          "diff {$options} --raw %s --",
          $this->repositoryHasNoCommits
            ? self::GIT_MAGIC_ROOT_COMMIT
            : 'HEAD',
        ));

      // Untracked files
      $untracked_future = $this->buildLocalFuture(
        array(
          'ls-files --others --exclude-standard',
        ));

      // TODO: This doesn't list unstaged adds. It's not clear how to get that
      // list other than "git status --porcelain" and then parsing it. :/

      // Unstaged changes
      $unstaged_future = $this->buildLocalFuture(
        array(
          'ls-files -m',
        ));

      $futures = array(
        $committed_future,
        $uncommitted_future,
        $untracked_future,
        $unstaged_future
      );
      Futures($futures)->resolveAll();


      // -- read back and process the results

      list($stdout, $stderr) = $committed_future->resolvex();
      $files = $this->parseGitStatus($stdout);

      list($stdout, $stderr) = $uncommitted_future->resolvex();
      $uncommitted_files = $this->parseGitStatus($stdout);
      foreach ($uncommitted_files as $path => $mask) {
        $mask |= self::FLAG_UNCOMMITTED;
        if (!isset($files[$path])) {
          $files[$path] = 0;
        }
        $files[$path] |= $mask;
      }

      list($stdout, $stderr) = $untracked_future->resolvex();
      $stdout = rtrim($stdout, "\n");
      if (strlen($stdout)) {
        $stdout = explode("\n", $stdout);
        foreach ($stdout as $file) {
          $files[$file] = self::FLAG_UNTRACKED;
        }
      }

      list($stdout, $stderr) = $unstaged_future->resolvex();
      $stdout = rtrim($stdout, "\n");
      if (strlen($stdout)) {
        $stdout = explode("\n", $stdout);
        foreach ($stdout as $file) {
          $files[$file] = isset($files[$file])
            ? ($files[$file] | self::FLAG_UNSTAGED)
            : self::FLAG_UNSTAGED;
        }
      }

      $this->status = $files;
    }

    return $this->status;
  }

  public function amendGitHeadCommit($message) {
    $this->execxLocal(
      'commit --amend --allow-empty --message %s',
      $message);
  }

  public function getPreReceiveHookStatus($old_ref, $new_ref) {
    $options = $this->getDiffBaseOptions();
    list($stdout) = $this->execxLocal(
      "diff {$options} --raw %s %s --",
      $old_ref,
      $new_ref);
    return $this->parseGitStatus($stdout, $full = true);
  }

  private function parseGitStatus($status, $full = false) {
    static $flags = array(
      'A' => self::FLAG_ADDED,
      'M' => self::FLAG_MODIFIED,
      'D' => self::FLAG_DELETED,
    );

    $status = trim($status);
    $lines = array();
    foreach (explode("\n", $status) as $line) {
      if ($line) {
        $lines[] = preg_split("/[ \t]/", $line);
      }
    }

    $files = array();
    foreach ($lines as $line) {
      $mask = 0;
      $flag = $line[4];
      $file = $line[5];
      foreach ($flags as $key => $bits) {
        if ($flag == $key) {
          $mask |= $bits;
        }
      }
      if ($full) {
        $files[$file] = array(
          'mask' => $mask,
          'ref'  => rtrim($line[3], '.'),
        );
      } else {
        $files[$file] = $mask;
      }
    }

    return $files;
  }

  public function getBlame($path) {
    // TODO: 'git blame' supports --porcelain and we should probably use it.
    list($stdout) = $this->execxLocal(
      'blame --date=iso -w -M %s -- %s',
      $this->getRelativeCommit(),
      $path);

    $blame = array();
    foreach (explode("\n", trim($stdout)) as $line) {
      if (!strlen($line)) {
        continue;
      }

      // lines predating a git repo's history are blamed to the oldest revision,
      // with the commit hash prepended by a ^. we shouldn't count these lines
      // as blaming to the oldest diff's unfortunate author
      if ($line[0] == '^') {
        continue;
      }

      $matches = null;
      $ok = preg_match(
        '/^([0-9a-f]+)[^(]+?[(](.*?) +\d\d\d\d-\d\d-\d\d/',
        $line,
        $matches);
      if (!$ok) {
        throw new Exception("Bad blame? `{$line}'");
      }
      $revision = $matches[1];
      $author = $matches[2];

      $blame[] = array($author, $revision);
    }

    return $blame;
  }

  public function getOriginalFileData($path) {
    return $this->getFileDataAtRevision($path, $this->getRelativeCommit());
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
        '/^(\d{6}) (blob|tree) ([a-z0-9]{40})[\t](.*)$/',
        $line,
        $matches);
      if (!$ok) {
        throw new Exception("Failed to parse git ls-tree output!");
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

    list($stdout) = $this->execxLocal(
      'ls-tree %s -- %s',
      $revision,
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
      'cat-file blob %s',
       $info[$path]['ref']);
    return $stdout;
  }

  /**
   * Returns names of all the branches in the current repository.
   *
   * @return array where each element is a triple ('name', 'sha1', 'current')
   */
  public function getAllBranches() {
    list($branch_info) = $this->execxLocal('branch --no-color');
    $lines = explode("\n", trim($branch_info));
    $result = array();
    foreach ($lines as $line) {
      $match = array();
      preg_match('/^(\*?)\s*(.*)$/', $line, $match);
      $name = $match[2];
      if ($name == '(no branch)') {
        // Just ignore this, we could theoretically try to figure out the ref
        // and treat it like a real branch but that's sort of ridiculous.
        continue;
      }
      $result[] = array(
        'current' => !empty($match[1]),
        'name'    => $name,
      );
    }
    $all_names = ipull($result, 'name');
    // Calling 'git branch' first and then 'git rev-parse' is way faster than
    // 'git branch -v' for some reason.
    list($sha1s_string) = $this->execxLocal('rev-parse %Ls', $all_names);

    $sha1_map = array_combine($all_names, explode("\n", trim($sha1s_string)));
    foreach ($result as &$branch) {
      $branch['sha1'] = $sha1_map[$branch['name']];
    }
    return $result;
  }

  /**
   * Returns git commit messages for the given revisions,
   * in the specified format (see git show --help for options).
   *
   * @param array $revs a list of commit hashes
   * @param string $format the format to show messages in
   */
  public function multigetCommitMessages($revs, $format) {

    list($commits_string) = $this->execxLocal(
      "show -s --pretty='format:'%s%s %Ls",
      $format,
      '%x00',
      $revs);

    $commits_list = array_slice(explode("\0", $commits_string), 0, -1);
    $commits_list = array_combine($revs, $commits_list);
    return $commits_list;
  }

  public function getRepositoryOwner() {
    list($owner) = $this->execxLocal('config --get user.name');
    return trim($owner);
  }

  public function getWorkingCopyRevision() {
    list($stdout) = $this->execxLocal('rev-parse HEAD');
    return rtrim($stdout, "\n");
  }

  public function supportsRelativeLocalCommits() {
    return true;
  }

  public function hasLocalCommit($commit) {
    try {
      $this->getCanonicalRevisionName($commit);
    } catch (CommandException $exception) {
      return false;
    }
    return true;
  }

  public function parseRelativeLocalCommit(array $argv) {
    if (count($argv) == 0) {
      return;
    }
    if (count($argv) != 1) {
      throw new ArcanistUsageException("Specify only one commit.");
    }
    $base = reset($argv);
    if ($base == ArcanistGitAPI::GIT_MAGIC_ROOT_COMMIT) {
      $merge_base = $base;
    } else {
      list($err, $merge_base) = $this->execManualLocal(
        'merge-base %s HEAD',
        $base);
      if ($err) {
        throw new ArcanistUsageException(
          "Unable to find any git commit named '{$base}' in this repository.");
      }
    }
    $this->setRelativeCommit(trim($merge_base));
  }

  public function getAllLocalChanges() {
    $diff = $this->getFullGitDiff();
    if (!strlen(trim($diff))) {
      return array();
    }
    $parser = new ArcanistDiffParser();
    return $parser->parseDiff($diff);
  }

  public function supportsLocalBranchMerge() {
    return true;
  }

  public function performLocalBranchMerge($branch, $message) {
    if (!$branch) {
      throw new ArcanistUsageException(
        "Under git, you must specify the branch you want to merge.");
    }
    $err = phutil_passthru(
      '(cd %s && git merge --no-ff -m %s %s)',
      $this->getPath(),
      $message,
      $branch);

    if ($err) {
      throw new ArcanistUsageException("Merge failed!");
    }
  }

  public function getFinalizedRevisionMessage() {
    return "You may now push this commit upstream, as appropriate (e.g. with ".
           "'git push', or 'git svn dcommit', or by printing and faxing it).";
  }

  public function getCommitMessageForRevision($rev) {
    list($message) = $this->execxLocal(
      'log -n1 %s',
      $rev);
    $parser = new ArcanistDiffParser();
    return head($parser->parseDiff($message));
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
    $revision_ids = array();
    foreach ($messages as $message) {
      $object = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $message->getMetadata('message'));
      if ($object->getRevisionID()) {
        $revision_ids[] = $object->getRevisionID();
      }
    }

    if ($revision_ids) {
      $results = $conduit->callMethodSynchronous(
        'differential.query',
        $query + array(
          'ids' => $revision_ids,
        ));
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

    if ($results) {
      return $results;
    }

    // If we still didn't succeed, try to find revisions by branch name.
    $results = $conduit->callMethodSynchronous(
      'differential.query',
      $query + array(
        'branches' => array($this->getBranchName()),
      ));

    return $results;
  }

  public function updateWorkingCopy() {
    $this->execxLocal('pull');
  }

}
