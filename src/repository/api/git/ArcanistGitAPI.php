<?php

/*
 * Copyright 2011 Facebook, Inc.
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
class ArcanistGitAPI extends ArcanistRepositoryAPI {

  private $status;
  private $relativeCommit = null;
  const SEARCH_LENGTH_FOR_PARENT_REVISIONS = 16;

  /**
   * For the repository's initial commit, 'git diff HEAD^' and similar do
   * not work. Using this instead does work.
   */
  const GIT_MAGIC_ROOT_COMMIT = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

  public static function newHookAPI($root) {
    return new ArcanistGitAPI($root);
  }

  public function getSourceControlSystemName() {
    return 'git';
  }

  public function setRelativeCommit($relative_commit) {
    $this->relativeCommit = $relative_commit;
    return $this;
  }

  public function getRelativeCommit() {
    if ($this->relativeCommit === null) {
      list($err) = exec_manual(
        '(cd %s; git rev-parse --verify HEAD^)',
        $this->getPath());
      if ($err) {
        $this->relativeCommit = self::GIT_MAGIC_ROOT_COMMIT;
      } else {
        $this->relativeCommit = 'HEAD^';
      }
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
    list($stdout) = execx(
      "(cd %s; git diff {$options} %s --)",
      $this->getPath(),
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getRawDiffText($path) {
    $relative_commit = $this->getRelativeCommit();
    $options = $this->getDiffFullOptions();
    list($stdout) = execx(
      "(cd %s; git diff {$options} %s -- %s)",
      $this->getPath(),
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
    list($stdout) = execx(
      '(cd %s; git branch)',
      $this->getPath());

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
    if ($relative == self::GIT_MAGIC_ROOT_COMMIT) {
      list($stdout) = execx(
        '(cd %s; git log --format=medium HEAD)',
        $this->getPath());
    } else {
      list($stdout) = execx(
        '(cd %s; git log --first-parent --format=medium %s..HEAD)',
        $this->getPath(),
        $this->getRelativeCommit());
    }
    return $stdout;
  }

  public function getGitHistoryLog() {
    list($stdout) = execx(
      '(cd %s; git log --format=medium -n%d %s)',
      $this->getPath(),
      self::SEARCH_LENGTH_FOR_PARENT_REVISIONS,
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getSourceControlBaseRevision() {
    list($stdout) = execx(
      '(cd %s; git rev-parse %s)',
      $this->getPath(),
      $this->getRelativeCommit());
    return rtrim($stdout, "\n");
  }

  /**
   * Returns the sha1 of the HEAD revision
   * @param boolean $short whether return the abbreviated or full hash.
   */
  public function getGitHeadRevision($short=false) {
    if ($short) {
      $flags = '--short';
    } else {
      $flags = '';
    }
    list($stdout) = execx(
      '(cd %s; git rev-parse %s HEAD)',
      $this->getPath(),
      $flags);
    return rtrim($stdout, "\n");
  }

  public function getWorkingCopyStatus() {
    if (!isset($this->status)) {

      $options = $this->getDiffBaseOptions();

      // Find committed changes.
      list($stdout) = execx(
        "(cd %s; git diff {$options} --raw %s --)",
        $this->getPath(),
        $this->getRelativeCommit());
      $files = $this->parseGitStatus($stdout);

      // Find uncommitted changes.
      list($stdout) = execx(
        "(cd %s; git diff {$options} --raw HEAD --)",
        $this->getPath());
      $uncommitted_files = $this->parseGitStatus($stdout);
      foreach ($uncommitted_files as $path => $mask) {
        $mask |= self::FLAG_UNCOMMITTED;
        if (!isset($files[$path])) {
          $files[$path] = 0;
        }
        $files[$path] |= $mask;
      }

      // Find untracked files.
      list($stdout) = execx(
        '(cd %s; git ls-files --others --exclude-standard)',
        $this->getPath());
      $stdout = rtrim($stdout, "\n");
      if (strlen($stdout)) {
        $stdout = explode("\n", $stdout);
        foreach ($stdout as $file) {
          $files[$file] = self::FLAG_UNTRACKED;
        }
      }

      // TODO: This doesn't list unstaged adds. It's not clear how to get that
      // list other than "git status --porcelain" and then parsing it. :/

      // Find unstaged changes.
      list($stdout) = execx(
        '(cd %s; git ls-files -m)',
        $this->getPath());
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
    execx(
      '(cd %s; git commit --amend --message %s)',
      $this->getPath(),
      $message);
  }

  public function getPreReceiveHookStatus($old_ref, $new_ref) {
    $options = $this->getDiffBaseOptions();
    list($stdout) = execx(
      "(cd %s && git diff {$options} --raw %s %s --)",
      $this->getPath(),
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
    list($stdout) = execx(
      '(cd %s; git blame -w -C %s -- %s)',
      $this->getPath(),
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

    list($stdout) = execx(
      '(cd %s && git ls-tree %s -- %s)',
      $this->getPath(),
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

    list($stdout) = execx(
      '(cd %s && git cat-file blob %s)',
       $this->getPath(),
       $info[$path]['ref']);
    return $stdout;
  }

  /**
   * Returns names of all the branches in the current repository.
   *
   * @return array where each element is a triple ('name', 'sha1', 'current')
   */
  public function getAllBranches() {
    list($branch_info) = execx(
      'cd %s && git branch --no-color', $this->getPath());
    $lines = explode("\n", trim($branch_info));
    $result = array();
    foreach ($lines as $line) {
      $match = array();
      $branch = array();
      preg_match('/^(\*?)\s*(\S+)/', $line, $match);
      $branch['current'] = !empty($match[1]);
      $branch['name'] = $match[2];
      $result[] = $branch;
    }
    $all_names = ipull($result, 'name');
    $names_list = implode(' ', $all_names);
    // Calling 'git branch' first and then 'git rev-parse' is way faster than
    // 'git branch -v' for some reason.
    list($sha1s_string) = execx(
      "cd %s && git rev-parse $names_list",
      $this->path);
    $sha1_map = array_combine($all_names, explode("\n", trim($sha1s_string)));
    foreach ($result as &$branch) {
      $branch['sha1'] = $sha1_map[$branch['name']];
    }
    return $result;
  }

  public function multigetRevForBranch($branch_names) {
    $names_list = implode(' ', $branch_names);
    list($sha1s_string) = execx(
      "cd %s && git rev-parse $names_list",
      $this->path);
    return array_combine($branch_names, explode("\n", trim($sha1s_string)));
  }

  /**
   * Returns git commit messages for the given revisions,
   * in the specified format (see git show --help for options).
   *
   * @param array $revs a list of commit hashes
   * @param string $format the format to show messages in
   */
  public function multigetCommitMessages($revs, $format) {
    $delimiter = "%%x00";
    $revs_list = implode(' ', $revs);
    $show_command =
      "git show -s --pretty=\"format:$format$delimiter\" $revs_list";
    list($commits_string) = execx(
      "cd %s && $show_command",
      $this->getPath());
    $commits_list = array_slice(explode("\0", $commits_string), 0, -1);
    $commits_list = array_combine($revs, $commits_list);
    return $commits_list;
  }

  public function getRepositoryOwner() {
    list($owner) = execx(
      'cd %s && git config --get user.name',
      $this->getPath());
    return trim($owner);
  }

}
