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
 * Interfaces with the Mercurial working copies.
 *
 * @group workingcopy
 */
class ArcanistMercurialAPI extends ArcanistRepositoryAPI {

  private $status;
  private $base;
  private $relativeCommit;

  public function getSourceControlSystemName() {
    return 'hg';
  }

  public function getSourceControlBaseRevision() {
    list($stdout) = execx(
      '(cd %s && hg id -ir %s)',
      $this->getPath(),
      $this->getRelativeCommit());
    return $stdout;
  }

  public function getSourceControlPath() {
    return '/';
  }

  public function getBranchName() {
    // TODO: I have nearly no idea how hg local branches work.
    list($stdout) = execx(
      '(cd %s && hg branch)',
      $this->getPath());
    return $stdout;
  }

  public function setRelativeCommit($commit) {
    list($err) = exec_manual(
      '(cd %s && hg id -ir %s)',
      $this->getPath(),
      $commit);

    if ($err) {
      throw new ArcanistUsageException(
        "Commit '{$commit}' is not a valid Mercurial commit identifier.");
    }

    $this->relativeCommit = $commit;
    return $this;
  }

  public function getRelativeCommit() {
    if (empty($this->relativeCommit)) {
      list($stdout) = execx(
        '(cd %s && hg outgoing --limit 1)',
        $this->getPath());
      $logs = $this->parseMercurialLog($stdout);
      if (!count($logs)) {
        throw new ArcanistUsageException("You have no outgoing changes!");
      }
      $oldest_log = head($logs);

      $this->relativeCommit = $oldest_log['rev'].'~1';
    }
    return $this->relativeCommit;
  }

  public function getBlame($path) {
    list($stdout) = execx(
      '(cd %s && hg blame -u -v -c --rev %s -- %s)',
      $this->getPath(),
      $this->getRelativeCommit(),
      $path);

    $blame = array();
    foreach (explode("\n", trim($stdout)) as $line) {
      if (!strlen($line)) {
        continue;
      }

      $matches = null;
      $ok = preg_match('^/\s*([^:]+?) [a-f0-9]{12}: (.*)$/', $line, $matches);

      if (!$ok) {
        throw new Exception("Unable to parse Mercurial blame line: {$line}");
      }

      $revision = $matches[2];
      $author = trim($matches[1]);
      $blame[] = array($author, $revision);
    }

    return $blame;
  }

  public function getWorkingCopyStatus() {

    // A reviewable revision spans multiple local commits in Mercurial, but
    // there is no way to get file change status across multiple commits, so
    // just take the entire diff and parse it to figure out what's changed.

    $diff = $this->getFullMercurialDiff();
    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($diff);

    $status_map = array();

    foreach ($changes as $change) {
      $flags = 0;
      switch ($change->getType()) {
        case ArcanistDiffChangeType::TYPE_ADD:
        case ArcanistDiffChangeType::TYPE_MOVE_HERE:
        case ArcanistDiffChangeType::TYPE_COPY_HERE:
          $flags |= self::FLAG_ADDED;
          break;
        case ArcanistDiffChangeType::TYPE_CHANGE:
        case ArcanistDiffChangeType::TYPE_COPY_AWAY: // Check for changes?
          $flags |= self::FLAG_MODIFIED;
          break;
        case ArcanistDiffChangeType::TYPE_DELETE:
        case ArcanistDiffChangeType::TYPE_MOVE_AWAY:
        case ArcanistDiffChangeType::TYPE_MULTICOPY:
          $flags |= self::FLAG_DELETED;
          break;
      }
      $status_map[$change->getCurrentPath()] = $flags;
    }

    list($stdout) = execx(
      '(cd %s && hg status)',
      $this->getPath());

    $working_status = $this->parseMercurialStatus($stdout);
    foreach ($working_status as $path => $status) {
      $status |= self::FLAG_UNCOMMITTED;
      if (!empty($status_map[$path])) {
        $status_map[$path] |= $status;
      } else {
        $status_map[$path] = $status;
      }
    }

    return $status_map;
  }

  private function getDiffOptions() {
    $options = array(
      '-g',
      '-U'.$this->getDiffLinesOfContext(),
    );
    return implode(' ', $options);
  }

  public function getRawDiffText($path) {
    $options = $this->getDiffOptions();

    list($stdout) = execx(
      '(cd %s && hg diff %C --rev %s --rev tip -- %s)',
      $this->getPath(),
      $options,
      $this->getRelativeCommit(),
      $path);

    return $stdout;
  }

  public function getFullMercurialDiff() {
    $options = $this->getDiffOptions();

    list($stdout) = execx(
      '(cd %s && hg diff %C --rev %s --rev tip --)',
      $this->getPath(),
      $options,
      $this->getRelativeCommit());

    return $stdout;
  }

  public function getOriginalFileData($path) {
    return $this->getFileDataAtRevision($path, $this->getRelativeCommit());
  }

  public function getCurrentFileData($path) {
    return $this->getFileDataAtRevision($path, 'tip');
  }

  private function getFileDataAtRevision($path, $revision) {
    list($stdout) = execx(
      '(cd %s && hg cat --rev %s -- %s)',
      $this->getPath(),
      $path);
    return $stdout;
  }

  private function parseMercurialStatus($status) {
    $result = array();

    $status = trim($status);
    if (!strlen($status)) {
      return $result;
    }

    $lines = explode("\n", $status);
    foreach ($lines as $line) {
      $flags = 0;
      list($code, $path) = explode(' ', $line, 2);
      switch ($code) {
        case 'A':
          $flags |= self::FLAG_ADDED;
          break;
        case 'R':
          $flags |= self::FLAG_REMOVED;
          break;
        case 'M':
          $flags |= self::FLAG_MODIFIED;
          break;
        case 'C':
          // This is "clean" and included only for completeness, these files
          // have not been changed.
          break;
        case '!':
          $flags |= self::FLAG_MISSING;
          break;
        case '?':
          $flags |= self::FLAG_UNTRACKED;
          break;
        case 'I':
          // This is "ignored" and included only for completeness.
          break;
        default:
          throw new Exception("Unknown Mercurial status '{$code}'.");
      }

      $result[$path] = $flags;
    }

    return $result;
  }

  private function parseMercurialLog($log) {
    $result = array();

    $chunks = explode("\n\n", trim($log));
    foreach ($chunks as $chunk) {
      $commit = array();
      $lines = explode("\n", $chunk);
      foreach ($lines as $line) {
        if (preg_match('/^(comparing with|searching for changes)/', $line)) {
          // These are sent to stdout when you run "hg outgoing" although the
          // format is otherwise identical to "hg log".
          continue;
        }
        list($name, $value) = explode(':', $line, 2);
        $value = trim($value);
        switch ($name) {
          case 'user':
            $commit['user'] = $value;
            break;
          case 'date':
            $commit['date'] = strtotime($value);
            break;
          case 'summary':
            $commit['summary'] = $value;
            break;
          case 'changeset':
            list($local, $rev) = explode(':', $value, 2);
            $commit['local'] = $local;
            $commit['rev'] = $rev;
            break;
          default:
            throw new Exception("Unknown Mercurial log field '{$name}'!");
        }
      }
      $result[] = $commit;
    }

    return $result;
  }

}
