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

  private function getDiffOptions() {
    $options = array(
      '-M',
      '-C',
      '--no-ext-diff',
      '--no-color',
      '--src-prefix=a/',
      '--dst-prefix=b/',
      '-U'.$this->getDiffLinesOfContext(),
    );
    return implode(' ', $options);
  }

  public function getFullGitDiff() {
    $options = $this->getDiffOptions();
    list($stdout) = execx(
      "(cd %s; git diff {$options} %s --)",
      $this->getPath(),
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getRawDiffText($path) {
    $relative_commit = $this->getRelativeCommit();
    $options = $this->getDiffOptions();
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
        '(cd %s; git log HEAD)',
        $this->getPath());
    } else {
      list($stdout) = execx(
        '(cd %s; git log %s..HEAD)',
        $this->getPath(),
        $this->getRelativeCommit());
    }
    return $stdout;
  }

  public function getGitHistoryLog() {
    list($stdout) = execx(
      '(cd %s; git log -n%d %s)',
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

  public function getGitHeadRevision() {
    list($stdout) = execx(
      '(cd %s; git rev-parse HEAD)',
      $this->getPath());
    return rtrim($stdout, "\n");
  }

  public function getWorkingCopyStatus() {
    if (!isset($this->status)) {

      // Find committed changes.
      list($stdout) = execx(
        '(cd %s; git diff --no-ext-diff --raw %s --)',
        $this->getPath(),
        $this->getRelativeCommit());
      $files = $this->parseGitStatus($stdout);

      // Find uncommitted changes.
      list($stdout) = execx(
        '(cd %s; git diff --no-ext-diff --raw HEAD --)',
        $this->getPath());
      $files += $this->parseGitStatus($stdout);

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

      // Find unstaged changes.
      list($stdout) = execx(
        '(cd %s; git ls-files -m)',
        $this->getPath());
      $stdout = rtrim($stdout, "\n");
      if (strlen($stdout)) {
        $stdout = explode("\n", $stdout);
        foreach ($stdout as $file) {
          $files[$file] = self::FLAG_UNSTAGED;
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
    list($stdout) = execx(
      '(cd %s && git diff --no-ext-diff --raw %s %s --)',
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

}
