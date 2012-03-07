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
 * Parses diffs from a working copy.
 *
 * @group diff
 */
final class ArcanistDiffParser {

  protected $api;
  protected $text;
  protected $line;
  protected $isGit;
  protected $isMercurial;
  protected $detectBinaryFiles = false;
  protected $tryEncoding;

  protected $changes = array();
  private $forcePath;

  protected function setRepositoryAPI(ArcanistRepositoryAPI $api) {
    $this->api = $api;
    return $this;
  }

  protected function getRepositoryAPI() {
    return $this->api;
  }

  public function setDetectBinaryFiles($detect) {
    $this->detectBinaryFiles = $detect;
    return $this;
  }

  public function setTryEncoding($encoding) {
    $this->tryEncoding = $encoding;
  }

  public function forcePath($path) {
    $this->forcePath = $path;
    return $this;
  }

  public function setChanges(array $changes) {
    $this->changes = mpull($changes, null, 'getCurrentPath');
    return $this;
  }

  public function parseSubversionDiff(ArcanistSubversionAPI $api, $paths) {
    $this->setRepositoryAPI($api);

    $diffs = array();

    foreach ($paths as $path => $status) {
      if ($status & ArcanistRepositoryAPI::FLAG_UNTRACKED ||
          $status & ArcanistRepositoryAPI::FLAG_CONFLICT ||
          $status & ArcanistRepositoryAPI::FLAG_MISSING) {
        unset($paths[$path]);
      }
    }

    $root = null;
    $from = array();
    foreach ($paths as $path => $status) {
      $change = $this->buildChange($path);

      if ($status & ArcanistRepositoryAPI::FLAG_ADDED) {
        $change->setType(ArcanistDiffChangeType::TYPE_ADD);
      } else if ($status & ArcanistRepositoryAPI::FLAG_DELETED) {
        $change->setType(ArcanistDiffChangeType::TYPE_DELETE);
      } else {
        $change->setType(ArcanistDiffChangeType::TYPE_CHANGE);
      }

      $is_dir = is_dir($api->getPath($path));
      if ($is_dir) {
        $change->setFileType(ArcanistDiffChangeType::FILE_DIRECTORY);
        // We have to go hit the diff even for directories because they may
        // have property changes or moves, etc.
      }
      $is_link = is_link($api->getPath($path));
      if ($is_link) {
        $change->setFileType(ArcanistDiffChangeType::FILE_SYMLINK);
      }

      $diff = $api->getRawDiffText($path);
      if ($diff) {
        $this->parseDiff($diff);
      }

      $info = $api->getSVNInfo($path);
      if (idx($info, 'Copied From URL')) {
        if (!$root) {
          $rinfo = $api->getSVNInfo('.');
          $root = $rinfo['URL'].'/';
        }
        $cpath = $info['Copied From URL'];
        $cpath = substr($cpath, strlen($root));
        if ($info['Copied From Rev']) {
          // The user can "svn cp /path/to/file@12345 x", which pulls a file out
          // of version history at a specific revision. If we just use the path,
          // we'll collide with possible changes to that path in the working
          // copy below. In particular, "svn cp"-ing a path which no longer
          // exists somewhere in the working copy and then adding that path
          // gets us to the "origin change type" branches below with a
          // TYPE_ADD state on the path. To avoid this, append the origin
          // revision to the path so we'll necessarily generate a new change.
          // TODO: In theory, you could have an '@' in your path and this could
          // cause a collision, e.g. two files named 'f' and 'f@12345'. This is
          // at least somewhat the user's fault, though.
          if ($info['Copied From Rev'] != $info['Revision']) {
            $cpath .= '@'.$info['Copied From Rev'];
          }
        }
        $change->setOldPath($cpath);

        $from[$path] = $cpath;
      }
    }

    foreach ($paths as $path => $status) {
      $change = $this->buildChange($path);
      if (empty($from[$path])) {
        continue;
      }

      if (empty($this->changes[$from[$path]])) {
        if ($change->getType() == ArcanistDiffChangeType::TYPE_COPY_HERE) {
          // If the origin path wasn't changed (or isn't included in this diff)
          // and we only copied it, don't generate a changeset for it. This
          // keeps us out of trouble when we go to 'arc commit' and need to
          // figure out which files should be included in the commit list.
          continue;
        }
      }

      $origin = $this->buildChange($from[$path]);
      $origin->addAwayPath($change->getCurrentPath());

      $type = $origin->getType();
      switch ($type) {
        case ArcanistDiffChangeType::TYPE_MULTICOPY:
        case ArcanistDiffChangeType::TYPE_COPY_AWAY:
        // "Add" is possible if you do some bizarre tricks with svn:ignore and
        // "svn copy"'ing URLs straight from the repository; you can end up with
        // a file that is a copy of itself. See T271.
        case ArcanistDiffChangeType::TYPE_ADD:
          break;
        case ArcanistDiffChangeType::TYPE_DELETE:
          $origin->setType(ArcanistDiffChangeType::TYPE_MOVE_AWAY);
          break;
        case ArcanistDiffChangeType::TYPE_MOVE_AWAY:
          $origin->setType(ArcanistDiffChangeType::TYPE_MULTICOPY);
          break;
        case ArcanistDiffChangeType::TYPE_CHANGE:
          $origin->setType(ArcanistDiffChangeType::TYPE_COPY_AWAY);
          break;
        default:
          throw new Exception("Bad origin state {$type}.");
      }

      $type = $origin->getType();
      switch ($type) {
        case ArcanistDiffChangeType::TYPE_MULTICOPY:
        case ArcanistDiffChangeType::TYPE_MOVE_AWAY:
          $change->setType(ArcanistDiffChangeType::TYPE_MOVE_HERE);
          break;
        case ArcanistDiffChangeType::TYPE_ADD:
        case ArcanistDiffChangeType::TYPE_COPY_AWAY:
          $change->setType(ArcanistDiffChangeType::TYPE_COPY_HERE);
          break;
        default:
          throw new Exception("Bad origin state {$type}.");
      }
    }

    return $this->changes;
  }

  public function parseDiff($diff) {
    $this->didStartParse($diff);

    if ($this->getLine() === null) {
      $this->didFailParse("Can't parse an empty diff!");
    }

    do {
      $patterns = array(
        // This is a normal SVN text change, probably from "svn diff".
        '(?P<type>Index): (?P<cur>.+)',
        // This is an SVN property change, probably from "svn diff".
        '(?P<type>Property changes on): (?P<cur>.+)',
        // This is a git commit message, probably from "git show".
        '(?P<type>commit) (?P<hash>[a-f0-9]+)',
        // This is a git diff, probably from "git show" or "git diff".
        // Note that the filenames may appear quoted.
        '(?P<type>diff --git) '.
          '(?P<old>"?[abicwo12]/.+"?) '.
          '(?P<cur>"?[abicwo12]/.+"?)',
        // This is a unified diff, probably from "diff -u" or synthetic diffing.
        '(?P<type>---) (?P<old>.+)\s+\d{4}-\d{2}-\d{2}.*',
        '(?P<binary>Binary) files '.
          '(?P<old>.+)\s+\d{4}-\d{2}-\d{2} and '.
          '(?P<new>.+)\s+\d{4}-\d{2}-\d{2} differ.*',

        // This is a normal Mercurial text change, probably from "hg diff".
        '(?P<type>diff -r) (?P<hgrev>[a-f0-9]+) (?P<cur>.+)',
      );

      $ok = false;
      $line = $this->getLine();
      $match = null;
      foreach ($patterns as $pattern) {
        $ok = preg_match('@^'.$pattern.'$@', $line, $match);
        if ($ok) {
          break;
        }
      }

      if (!$ok) {
        $this->didFailParse(
          "Expected a hunk header, like 'Index: /path/to/file.ext' (svn), ".
          "'Property changes on: /path/to/file.ext' (svn properties), ".
          "'commit 59bcc3ad6775562f845953cf01624225' (git show), ".
          "'diff --git' (git diff), or '--- filename' (unified diff).");
      }

      if (isset($match['type'])) {
        if ($match['type'] == 'diff --git') {
          if (isset($match['old'])) {
            $match['old'] = $this->unescapeFilename($match['old']);
            $match['old'] = substr($match['old'], 2);
          }
          if (isset($match['cur'])) {
            $match['cur'] = $this->unescapeFilename($match['cur']);
            $match['cur'] = substr($match['cur'], 2);
          }
        }
      }

      $change = $this->buildChange(idx($match, 'cur'));

      if (isset($match['old'])) {
        $change->setOldPath($match['old']);
      }

      if (isset($match['hash'])) {
        $change->setCommitHash($match['hash']);
      }

      if (isset($match['binary'])) {
        $change->setFileType(ArcanistDiffChangeType::FILE_BINARY);
        $line = $this->nextNonemptyLine();
        continue;
      }

      $line = $this->nextLine();

      switch ($match['type']) {
        case 'Index':
          $this->parseIndexHunk($change);
          break;
        case 'Property changes on':
          $this->parsePropertyHunk($change);
          break;
        case 'diff --git':
          $this->setIsGit(true);
          $this->parseIndexHunk($change);
          break;
        case 'commit':
          $this->setIsGit(true);
          $this->parseCommitMessage($change);
          break;
        case '---':
          $ok = preg_match(
            '@^(?:\+\+\+) (.*)\s+\d{4}-\d{2}-\d{2}.*$@',
            $line,
            $match);
          if (!$ok) {
            $this->didFailParse("Expected '+++ filename' in unified diff.");
          }
          $change->setCurrentPath($match[1]);
          $line = $this->nextLine();
          $this->parseChangeset($change);
          break;
        case 'diff -r':
          $this->setIsMercurial(true);
          $this->parseIndexHunk($change);
          break;
        default:
          $this->didFailParse("Unknown diff type.");
      }
    } while ($this->getLine() !== null);

    $this->didFinishParse();

    return $this->changes;
  }

  protected function parseCommitMessage(ArcanistDiffChange $change) {
    $change->setType(ArcanistDiffChangeType::TYPE_MESSAGE);

    $message = array();

    $line = $this->getLine();
    if (preg_match('/^Merge: /', $line)) {
      $this->nextLine();
    }

    $line = $this->getLine();
    if (!preg_match('/^Author: /', $line)) {
      $this->didFailParse("Expected 'Author:'.");
    }

    $line = $this->nextLine();
    if (!preg_match('/^Date: /', $line)) {
      $this->didFailParse("Expected 'Date:'.");
    }

    while (($line = $this->nextLine()) !== null) {
      if (strlen($line) && $line[0] != ' ') {
        break;
      }
      // Strip leading spaces from Git commit messages.
      $message[] = substr($line, 4);
    }

    $message = rtrim(implode("\n", $message));
    $change->setMetadata('message', $message);
  }

  /**
   * Parse an SVN property change hunk. These hunks are ambiguous so just sort
   * of try to get it mostly right. It's entirely possible to foil this parser
   * (or any other parser) with a carefully constructed property change.
   */
  protected function parsePropertyHunk(ArcanistDiffChange $change) {
    $line = $this->getLine();
    if (!preg_match('/^_+$/', $line)) {
      $this->didFailParse("Expected '______________________'.");
    }

    $line = $this->nextLine();
    while ($line !== null) {
      $done = preg_match('/^(Index|Property changes on):/', $line);
      if ($done) {
        break;
      }

      $matches = null;
      $ok = preg_match('/^(Modified|Added|Deleted): (.*)$/', $line, $matches);
      if (!$ok) {
        $this->didFailParse("Expected 'Added', 'Deleted', or 'Modified'.");
      }

      $op = $matches[1];
      $prop = $matches[2];

      list($old, $new) = $this->parseSVNPropertyChange($op, $prop);

      if ($old !== null) {
        $change->setOldProperty($prop, $old);
      }

      if ($new !== null) {
        $change->setNewProperty($prop, $new);
      }

      $line = $this->getLine();
    }
  }

  private function parseSVNPropertyChange($op, $prop) {

    $old = array();
    $new = array();

    $target = null;

    $line = $this->nextLine();
    while ($line !== null) {
      $done = preg_match(
        '/^(Modified|Added|Deleted|Index|Property changes on):/',
        $line);
      if ($done) {
        break;
      }
      $trimline = ltrim($line);
      if ($trimline && $trimline[0] == '+') {
        if ($op == 'Deleted') {
          $this->didFailParse('Unexpected "+" section in property deletion.');
        }
        $target = 'new';
        $line = substr($trimline, 2);
      } else if ($trimline && $trimline[0] == '-') {
        if ($op == 'Added') {
          $this->didFailParse('Unexpected "-" section in property addition.');
        }
        $target = 'old';
        $line = substr($trimline, 2);
      } else if (!strncmp($trimline, 'Merged', 6)) {
        if ($op == 'Added') {
          $target = 'new';
        } else {
          // These can appear on merges. No idea how to interpret this (unclear
          // what the old / new values are) and it's of dubious usefulness so
          // just throw it away until someone complains.
          $target = null;
        }
        $line = $trimline;
      }

      if ($target == 'new') {
        $new[] = $line;
      } else if ($target == 'old') {
        $old[] = $line;
      }

      $line = $this->nextLine();
    }

    $old = rtrim(implode("\n", $old));
    $new = rtrim(implode("\n", $new));

    if (!strlen($old)) {
      $old = null;
    }

    if (!strlen($new)) {
      $new = null;
    }

    return array($old, $new);
  }

  protected function setIsGit($git) {
    if ($this->isGit !== null && $this->isGit != $git) {
      throw new Exception("Git status has changed!");
    }
    $this->isGit = $git;
    return $this;
  }

  protected function getIsGit() {
    return $this->isGit;
  }

  public function setIsMercurial($is_mercurial) {
    $this->isMercurial = $is_mercurial;
    return $this;
  }

  public function getIsMercurial() {
    return $this->isMercurial;
  }

  protected function parseIndexHunk(ArcanistDiffChange $change) {
    $is_git = $this->getIsGit();
    $is_mercurial = $this->getIsMercurial();
    $is_svn = (!$is_git && !$is_mercurial);

    $line = $this->getLine();
    if ($is_git) {
      do {

        $patterns = array(
          '(?P<new>new) file mode (?P<newmode>\d+)',
          '(?P<deleted>deleted) file mode (?P<oldmode>\d+)',
          // These occur when someone uses `chmod` on a file.
          'old mode (?P<oldmode>\d+)',
          'new mode (?P<newmode>\d+)',
          // These occur when you `mv` a file and git figures it out.
          'similarity index ',
          'rename from (?P<old>.*)',
          '(?P<move>rename) to (?P<cur>.*)',
          'copy from (?P<old>.*)',
          '(?P<copy>copy) to (?P<cur>.*)'
        );

        $ok = false;
        $match = null;
        foreach ($patterns as $pattern) {
          $ok = preg_match('@^'.$pattern.'@', $line, $match);
          if ($ok) {
            break;
          }
        }

        if (!$ok) {
          if ($line === null ||
              preg_match('/^(diff --git|commit) /', $line)) {
            // In this case, there are ONLY file mode changes, or this is a
            // pure move.
            return;
          }
          break;
        }

        if (!empty($match['oldmode'])) {
          $change->setOldProperty('unix:filemode', $match['oldmode']);
        }
        if (!empty($match['newmode'])) {
          $change->setNewProperty('unix:filemode', $match['newmode']);
        }

        if (!empty($match['deleted'])) {
          $change->setType(ArcanistDiffChangeType::TYPE_DELETE);
        }

        if (!empty($match['new'])) {
          // If you replace a symlink with a normal file, git renders the change
          // as a "delete" of the symlink plus an "add" of the new file. We
          // prefer to represent this as a change.
          if ($change->getType() == ArcanistDiffChangeType::TYPE_DELETE) {
            $change->setType(ArcanistDiffChangeType::TYPE_CHANGE);
          } else {
            $change->setType(ArcanistDiffChangeType::TYPE_ADD);
          }
        }

        if (!empty($match['old'])) {
          $match['old'] = $this->unescapeFilename($match['old']);
          $change->setOldPath($match['old']);
        }

        if (!empty($match['cur'])) {
          $match['cur'] = $this->unescapeFilename($match['cur']);
          $change->setCurrentPath($match['cur']);
        }

        if (!empty($match['copy'])) {
          $change->setType(ArcanistDiffChangeType::TYPE_COPY_HERE);
          $old = $this->buildChange($change->getOldPath());
          $type = $old->getType();

          if ($type == ArcanistDiffChangeType::TYPE_MOVE_AWAY) {
            $old->setType(ArcanistDiffChangeType::TYPE_MULTICOPY);
          } else {
            $old->setType(ArcanistDiffChangeType::TYPE_COPY_AWAY);
          }

          $old->addAwayPath($change->getCurrentPath());
        }

        if (!empty($match['move'])) {
          $change->setType(ArcanistDiffChangeType::TYPE_MOVE_HERE);
          $old = $this->buildChange($change->getOldPath());
          $type = $old->getType();

          if ($type == ArcanistDiffChangeType::TYPE_MULTICOPY) {
            // Great, no change.
          } else if ($type == ArcanistDiffChangeType::TYPE_MOVE_AWAY) {
            $old->setType(ArcanistDiffChangeType::TYPE_MULTICOPY);
          } else if ($type == ArcanistDiffChangeType::TYPE_COPY_AWAY) {
            $old->setType(ArcanistDiffChangeType::TYPE_MULTICOPY);
          } else {
            $old->setType(ArcanistDiffChangeType::TYPE_MOVE_AWAY);
          }

          $old->addAwayPath($change->getCurrentPath());
        }

        $line = $this->nextNonemptyLine();
      } while (true);
    }

    $line = $this->getLine();

    if ($is_svn) {
      $ok = preg_match('/^=+$/', $line);
      if (!$ok) {
        $this->didFailParse("Expected '=======================' divider line.");
      } else {
        // Adding an empty file in SVN can produce an empty line here.
        $line = $this->nextNonemptyLine();
      }
    } else if ($is_git) {
      $ok = preg_match('/^index .*$/', $line);
      if (!$ok) {
        // TODO: "hg diff -g" diffs ("mercurial git-style diffs") do not include
        // this line, so we can't parse them if we fail on it. Maybe introduce
        // a flag saying "parse this diff using relaxed git-style diff rules"?

        // $this->didFailParse("Expected 'index af23f...a98bc' header line.");
      } else {
        // NOTE: In the git case, where this patch is the last change in the
        // file, we may have a final terminal newline. Skip over it so that
        // we'll hit the '$line === null' block below. This is covered by the
        // 'git-empty-file.gitdiff' test case.
        $line = $this->nextNonemptyLine();
      }
    }

    // If there are files with only whitespace changes and -b or -w are
    // supplied as command-line flags to `diff', svn and git both produce
    // changes without any body.
    if ($line === null ||
        preg_match(
          '/^(Index:|Property changes on:|diff --git|commit) /',
          $line)) {
      return;
    }

    $is_binary_add = preg_match(
      '/^Cannot display: file marked as a binary type.$/',
      $line);
    if ($is_binary_add) {
      $this->nextLine(); // Cannot display: file marked as a binary type.
      $this->nextNonemptyLine(); // svn:mime-type = application/octet-stream
      $this->markBinary($change);
      return;
    }

    // We can get this in git, or in SVN when a file exists in the repository
    // WITHOUT a binary mime-type and is changed and given a binary mime-type.
    $is_binary_diff = preg_match(
      '/^Binary files .* and .* differ$/',
      $line);
    if ($is_binary_diff) {
      $this->nextNonemptyLine(); // Binary files x and y differ
      $this->markBinary($change);
      return;
    }

    // This occurs under "hg diff --git" when a binary file is removed. See
    // test case "hg-binary-delete.hgdiff". (I believe it never occurs under
    // git, which reports the "files X and /dev/null differ" string above. Git
    // can not apply these patches.)
    $is_hg_binary_delete = preg_match(
      '/^Binary file .* has changed$/',
      $line);
    if ($is_hg_binary_delete) {
      $this->nextNonemptyLine();
      $this->markBinary($change);
      return;
    }

    // With "git diff --binary" (not a normal mode, but one users may explicitly
    // invoke and then, e.g., copy-paste into the web console) or "hg diff
    // --git" (normal under hg workflows), we may encounter a literal binary
    // patch.
    $is_git_binary_patch = preg_match(
      '/^GIT binary patch$/',
      $line);
    if ($is_git_binary_patch) {
      $this->nextLine();
      $this->parseGitBinaryPatch();
      $line = $this->getLine();
      if (preg_match('/^literal/', $line)) {
        // We may have old/new binaries (change) or just a new binary (hg add).
        // If there are two blocks, parse both.
        $this->parseGitBinaryPatch();
      }
      $this->markBinary($change);
      return;
    }

    if ($is_git) {
      // "git diff -b" ignores whitespace, but has an empty hunk target
      if (preg_match('@^diff --git a/.*$@', $line)) {
        $this->nextLine();
        return null;
      }
    }

    $old_file = $this->parseHunkTarget();
    $new_file = $this->parseHunkTarget();

    $change->setOldPath($old_file);

    $this->parseChangeset($change);
  }

  private function parseGitBinaryPatch() {

    // TODO: We could decode the patches, but it's a giant mess so don't bother
    // for now. We'll pick up the data from the working copy in the common
    // case ("arc diff").

    $line = $this->getLine();
    if (!preg_match('/^literal /', $line)) {
      $this->didFailParse("Expected 'literal NNNN' to start git binary patch.");
    }
    do {
      $line = $this->nextLine();
      if ($line === '' || $line === null) {
        // Some versions of Mercurial apparently omit the terminal newline,
        // although it's unclear if Git will ever do this. In either case,
        // rely on the base85 check for sanity.
        $this->nextNonemptyLine();
        return;
      } else if (!preg_match('/^[a-zA-Z]/', $line)) {
        $this->didFailParse("Expected base85 line length character (a-zA-Z).");
      }
    } while (true);
  }

  protected function parseHunkTarget() {
    $line = $this->getLine();
    $matches = null;

    $remainder = '(?:\s*\(.*\))?';
    if ($this->getIsMercurial()) {
      // Something like "Fri Aug 26 01:20:50 2005 -0700", don't bother trying
      // to parse it.
      $remainder = '\t.*';
    }

    $ok = preg_match(
      '@^[-+]{3} (?:[ab]/)?(?P<path>.*?)'.$remainder.'$@',
      $line,
      $matches);
    if (!$ok) {
      $this->didFailParse(
        "Expected hunk target '+++ path/to/file.ext (revision N)'.");
    }

    $this->nextLine();
    return $matches['path'];
  }

  protected function markBinary(ArcanistDiffChange $change) {
    $change->setFileType(ArcanistDiffChangeType::FILE_BINARY);
    return $this;
  }

  protected function parseChangeset(ArcanistDiffChange $change) {
    $all_changes = array();
    do {
      $hunk = new ArcanistDiffHunk();
      $line = $this->getLine();
      $real = array();

      // In the case where only one line is changed, the length is omitted.
      // The final group is for git, which appends a guess at the function
      // context to the diff.
      $matches = null;
      $ok = preg_match(
        '/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@(?: .*?)?$/U',
        $line,
        $matches);

      if (!$ok) {
        $this->didFailParse("Expected hunk header '@@ -NN,NN +NN,NN @@'.");
      }

      $hunk->setOldOffset($matches[1]);
      $hunk->setNewOffset($matches[3]);

      // Cover for the cases where length wasn't present (implying one line).
      $old_len = idx($matches, 2);
      if (!strlen($old_len)) {
        $old_len = 1;
      }
      $new_len = idx($matches, 4);
      if (!strlen($new_len)) {
        $new_len = 1;
      }

      $hunk->setOldLength($old_len);
      $hunk->setNewLength($new_len);

      $add = 0;
      $del = 0;

      $advance = false;
      while ((($line = $this->nextLine()) !== null)) {
        if (strlen($line)) {
          $char = $line[0];
        } else {
          $char = '~';
        }
        switch ($char) {
          case '\\':
            if (!preg_match('@\\ No newline at end of file@', $line)) {
              $this->didFailParse(
                "Expected '\ No newline at end of file'.");
            }
            if ($new_len) {
              $real[] = $line;
              $hunk->setIsMissingOldNewline(true);
            } else {
              $real[] = $line;
              $hunk->setIsMissingNewNewline(true);
            }
            if (!$new_len) {
              $advance = true;
              break 2;
            }
            break;
          case '+':
            if (!$new_len) {
              break 2;
            }
            ++$add;
            --$new_len;
            $real[] = $line;
            break;
          case '-':
            if (!$old_len) {
              break 2;
            }
            ++$del;
            --$old_len;
            $real[] = $line;
            break;
          case ' ':
            if (!$old_len && !$new_len) {
              break 2;
            }
            --$old_len;
            --$new_len;
            $real[] = $line;
            break;
          case '~':
            $advance = true;
            break 2;
          default:
            break 2;
        }
      }

      if ($old_len != 0 || $new_len != 0) {
        $this->didFailParse("Found the wrong number of hunk lines.");
      }

      $corpus = implode("\n", $real);

      $is_binary = false;
      if ($this->detectBinaryFiles) {
        $is_binary = !phutil_is_utf8($corpus);

        if ($is_binary && $this->tryEncoding) {
          $is_binary = ArcanistDiffUtils::isHeuristicBinaryFile($corpus);
          if (!$is_binary) {
              // NOTE: This feature is HIGHLY EXPERIMENTAL and will cause a lot
              // of issues. Use it at your own risk.
              $corpus = mb_convert_encoding(
                  $corpus, 'UTF-8', $this->tryEncoding);
              if (!phutil_is_utf8($corpus)) {
                  throw new Exception(
                      'Failed converting hunk to '.$this->tryEncoding);
              }
          }
        }

      }

      if ($is_binary) {
        // SVN happily treats binary files which aren't marked with the right
        // mime type as text files. Detect that junk here and mark the file
        // binary. We'll catch stuff with unicode too, but that's verboten
        // anyway. If there are too many false positives with this we might
        // need to make it threshold-triggered instead of triggering on any
        // unprintable byte.
        $change->setFileType(ArcanistDiffChangeType::FILE_BINARY);
      } else {
        $hunk->setCorpus($corpus);
        $hunk->setAddLines($add);
        $hunk->setDelLines($del);
        $change->addHunk($hunk);
      }

      if ($advance) {
        $line = $this->nextNonemptyLine();
      }

    } while (preg_match('/^@@ /', $line));
  }

  protected function buildChange($path = null) {
    $change = null;
    if ($path !== null) {
      if (!empty($this->changes[$path])) {
        return $this->changes[$path];
      }
    }

    if ($this->forcePath) {
      return $this->changes[$this->forcePath];
    }

    $change = new ArcanistDiffChange();
    if ($path !== null) {
      $change->setCurrentPath($path);
      $this->changes[$path] = $change;
    } else {
      $this->changes[] = $change;
    }

    return $change;
  }

  protected function didStartParse($text) {
    // TODO: Removed an fb_utf8ize() call here. -epriestley

    // Eat leading whitespace. This may happen if the first change in the diff
    // is an SVN property change.
    $text = ltrim($text);

    $this->text = explode("\n", $text);
    $this->line = 0;
  }

  protected function getLine() {
    if ($this->text === null) {
      throw new Exception("Not parsing!");
    }
    if (isset($this->text[$this->line])) {
      return $this->text[$this->line];
    }
    return null;
  }

  protected function nextLine() {
    $this->line++;
    return $this->getLine();
  }

  protected function nextNonemptyLine() {
    while (($line = $this->nextLine()) !== null) {
      if (strlen(trim($line)) !== 0) {
        break;
      }
    }
    return $this->getLine();
  }

  protected function didFinishParse() {
    $this->text = null;
  }

  protected function didFailParse($message) {
    $min = max(0, $this->line - 3);
    $max = min($this->line + 3, count($this->text) - 1);

    $context = '';
    for ($ii = $min; $ii <= $max; $ii++) {
      $context .= sprintf(
        "%8.8s %s\n",
        ($ii == $this->line) ? '>>>  ' : '',
        $this->text[$ii]);
    }

    $message = "Parse Exception: {$message}\n\n{$context}\n";
    throw new Exception($message);
  }

  /**
   * Unescape escaped filenames, e.g. from "git diff".
   */
  private function unescapeFilename($name) {
    if (preg_match('/^".+"$/', $name)) {
      return stripcslashes(substr($name, 1, -1));
    } else {
      return $name;
    }
  }
}
