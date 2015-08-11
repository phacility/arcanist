<?php

/**
 * Interfaces with the Mercurial working copies.
 */
final class ArcanistMercurialAPI extends ArcanistRepositoryAPI {

  private $branch;
  private $localCommitInfo;
  private $rawDiffCache = array();

  private $supportsRebase;
  private $supportsPhases;

  protected function buildLocalFuture(array $argv) {
    // Mercurial has a "defaults" feature which basically breaks automation by
    // allowing the user to add random flags to any command. This feature is
    // "deprecated" and "a bad idea" that you should "forget ... existed"
    // according to project lead Matt Mackall:
    //
    //  http://markmail.org/message/hl3d6eprubmkkqh5
    //
    // There is an HGPLAIN environmental variable which enables "plain mode"
    // and hopefully disables this stuff.

    if (phutil_is_windows()) {
      $argv[0] = 'set HGPLAIN=1 & hg '.$argv[0];
    } else {
      $argv[0] = 'HGPLAIN=1 hg '.$argv[0];
    }

    $future = newv('ExecFuture', $argv);
    $future->setCWD($this->getPath());
    return $future;
  }

  public function execPassthru($pattern /* , ... */) {
    $args = func_get_args();
    if (phutil_is_windows()) {
      $args[0] = 'hg '.$args[0];
    } else {
      $args[0] = 'HGPLAIN=1 hg '.$args[0];
    }

    return call_user_func_array('phutil_passthru', $args);
  }

  public function getSourceControlSystemName() {
    return 'hg';
  }

  public function getMetadataPath() {
    return $this->getPath('.hg');
  }

  public function getSourceControlBaseRevision() {
    return $this->getCanonicalRevisionName($this->getBaseCommit());
  }

  public function getCanonicalRevisionName($string) {
    $match = null;
    if ($this->isHgSubversionRepo() &&
        preg_match('/@([0-9]+)$/', $string, $match)) {
      $string = hgsprintf('svnrev(%s)', $match[1]);
    }

    list($stdout) = $this->execxLocal(
      'log -l 1 --template %s -r %s --',
      '{node}',
      $string);
    return $stdout;
  }

  public function getHashFromFromSVNRevisionNumber($revision_id) {
    $matches = array();
    $string = hgsprintf('svnrev(%s)', $revision_id);
    list($stdout) = $this->execxLocal(
      'log -l 1 --template %s -r %s --',
      '{node}',
       $string);
    if (!$stdout) {
      throw new ArcanistUsageException(
        pht('Cannot find the HG equivalent of %s given.', $revision_id));
    }
    return $stdout;
  }


  public function getSVNRevisionNumberFromHash($hash) {
    $matches = array();
    list($stdout) = $this->execxLocal(
      'log -r %s --template {svnrev}', $hash);
    if (!$stdout) {
      throw new ArcanistUsageException(
        pht('Cannot find the SVN equivalent of %s given.', $hash));
    }
    return $stdout;
  }

  public function getSourceControlPath() {
    return '/';
  }

  public function getBranchName() {
    if (!$this->branch) {
      list($stdout) = $this->execxLocal('branch');
      $this->branch = trim($stdout);
    }
    return $this->branch;
  }

  protected function didReloadCommitRange() {
    $this->localCommitInfo = null;
  }

  protected function buildBaseCommit($symbolic_commit) {
    if ($symbolic_commit !== null) {
      try {
        $commit = $this->getCanonicalRevisionName(
          hgsprintf('ancestor(%s,.)', $symbolic_commit));
      } catch (Exception $ex) {
        // Try it as a revset instead of a commit id
        try {
          $commit = $this->getCanonicalRevisionName(
            hgsprintf('ancestor(%R,.)', $symbolic_commit));
        } catch (Exception $ex) {
          throw new ArcanistUsageException(
            pht(
              "Commit '%s' is not a valid Mercurial commit identifier.",
              $symbolic_commit));
        }
      }

      $this->setBaseCommitExplanation(
        pht(
          'it is the greatest common ancestor of the working directory '.
          'and the commit you specified explicitly.'));
      return $commit;
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

    // Mercurial 2.1 and up have phases which indicate if something is
    // published or not. To find which revs are outgoing, it's much
    // faster to check the phase instead of actually checking the server.
    if ($this->supportsPhases()) {
      list($err, $stdout) = $this->execManualLocal(
        'log --branch %s -r %s --style default',
        $this->getBranchName(),
        'draft()');
    } else {
      list($err, $stdout) = $this->execManualLocal(
        'outgoing --branch %s --style default',
        $this->getBranchName());
    }

    if (!$err) {
      $logs = ArcanistMercurialParser::parseMercurialLog($stdout);
    } else {
      // Mercurial (in some versions?) raises an error when there's nothing
      // outgoing.
      $logs = array();
    }

    if (!$logs) {
      $this->setBaseCommitExplanation(
        pht(
          'you have no outgoing commits, so arc assumes you intend to submit '.
          'uncommitted changes in the working copy.'));
      return $this->getWorkingCopyRevision();
    }

    $outgoing_revs = ipull($logs, 'rev');

    // This is essentially an implementation of a theoretical `hg merge-base`
    // command.
    $against = $this->getWorkingCopyRevision();
    while (true) {
      // NOTE: The "^" and "~" syntaxes were only added in hg 1.9, which is
      // new as of July 2011, so do this in a compatible way. Also, "hg log"
      // and "hg outgoing" don't necessarily show parents (even if given an
      // explicit template consisting of just the parents token) so we need
      // to separately execute "hg parents".

      list($stdout) = $this->execxLocal(
        'parents --style default --rev %s',
        $against);
      $parents_logs = ArcanistMercurialParser::parseMercurialLog($stdout);

      list($p1, $p2) = array_merge($parents_logs, array(null, null));

      if ($p1 && !in_array($p1['rev'], $outgoing_revs)) {
        $against = $p1['rev'];
        break;
      } else if ($p2 && !in_array($p2['rev'], $outgoing_revs)) {
        $against = $p2['rev'];
        break;
      } else if ($p1) {
        $against = $p1['rev'];
      } else {
        // This is the case where you have a new repository and the entire
        // thing is outgoing; Mercurial literally accepts "--rev null" as
        // meaning "diff against the empty state".
        $against = 'null';
        break;
      }
    }

    if ($against == 'null') {
      $this->setBaseCommitExplanation(
        pht('this is a new repository (all changes are outgoing).'));
    } else {
      $this->setBaseCommitExplanation(
        pht(
          'it is the first commit reachable from the working copy state '.
          'which is not outgoing.'));
    }

    return $against;
  }

  public function getLocalCommitInformation() {
    if ($this->localCommitInfo === null) {
      $base_commit = $this->getBaseCommit();
      list($info) = $this->execxLocal(
        'log --template %s --rev %s --branch %s --',
        "{node}\1{rev}\1{author}\1".
          "{date|rfc822date}\1{branch}\1{tag}\1{parents}\1{desc}\2",
        hgsprintf('(%s::. - %s)', $base_commit, $base_commit),
        $this->getBranchName());
      $logs = array_filter(explode("\2", $info));

      $last_node = null;

      $futures = array();

      $commits = array();
      foreach ($logs as $log) {
        list($node, $rev, $full_author, $date, $branch, $tag,
          $parents, $desc) = explode("\1", $log, 9);

        list($author, $author_email) = $this->parseFullAuthor($full_author);

        // NOTE: If a commit has only one parent, {parents} returns empty.
        // If it has two parents, {parents} returns revs and short hashes, not
        // full hashes. Try to avoid making calls to "hg parents" because it's
        // relatively expensive.
        $commit_parents = null;
        if (!$parents) {
          if ($last_node) {
            $commit_parents = array($last_node);
          }
        }

        if (!$commit_parents) {
          // We didn't get a cheap hit on previous commit, so do the full-cost
          // "hg parents" call. We can run these in parallel, at least.
          $futures[$node] = $this->execFutureLocal(
            'parents --template %s --rev %s',
            '{node}\n',
            $node);
        }

        $commits[$node] = array(
          'author'  => $author,
          'time'    => strtotime($date),
          'branch'  => $branch,
          'tag'     => $tag,
          'commit'  => $node,
          'rev'     => $node, // TODO: Remove eventually.
          'local'   => $rev,
          'parents' => $commit_parents,
          'summary' => head(explode("\n", $desc)),
          'message' => $desc,
          'authorEmail' => $author_email,
        );

        $last_node = $node;
      }

      $futures = id(new FutureIterator($futures))
        ->limit(4);
      foreach ($futures as $node => $future) {
        list($parents) = $future->resolvex();
        $parents = array_filter(explode("\n", $parents));
        $commits[$node]['parents'] = $parents;
      }

      // Put commits in newest-first order, to be consistent with Git and the
      // expected order of "hg log" and "git log" under normal circumstances.
      // The order of ancestors() is oldest-first.
      $commits = array_reverse($commits);

      $this->localCommitInfo = $commits;
    }

    return $this->localCommitInfo;
  }

  public function getAllFiles() {
    // TODO: Handle paths with newlines.
    $future = $this->buildLocalFuture(array('manifest'));
    return new LinesOfALargeExecFuture($future);
  }

  public function getChangedFiles($since_commit) {
    list($stdout) = $this->execxLocal(
      'status --rev %s',
      $since_commit);
    return ArcanistMercurialParser::parseMercurialStatus($stdout);
  }

  public function getBlame($path) {
    list($stdout) = $this->execxLocal(
      'annotate -u -v -c --rev %s -- %s',
      $this->getBaseCommit(),
      $path);

    $lines = phutil_split_lines($stdout, $retain_line_endings = true);

    $blame = array();
    foreach ($lines as $line) {
      if (!strlen($line)) {
        continue;
      }

      $matches = null;
      $ok = preg_match('/^\s*([^:]+?) ([a-f0-9]{12}):/', $line, $matches);

      if (!$ok) {
        throw new Exception(
          pht(
            'Unable to parse Mercurial blame line: %s',
            $line));
      }

      $revision = $matches[2];
      $author = trim($matches[1]);
      $blame[] = array($author, $revision);
    }

    return $blame;
  }

  protected function buildUncommittedStatus() {
    list($stdout) = $this->execxLocal('status');

    $results = new PhutilArrayWithDefaultValue();

    $working_status = ArcanistMercurialParser::parseMercurialStatus($stdout);
    foreach ($working_status as $path => $mask) {
      if (!($mask & ArcanistRepositoryAPI::FLAG_UNTRACKED)) {
        // Mark tracked files as uncommitted.
        $mask |= self::FLAG_UNCOMMITTED;
      }

      $results[$path] |= $mask;
    }

    return $results->toArray();
  }

  protected function buildCommitRangeStatus() {
    // TODO: Possibly we should use "hg status --rev X --rev ." for this
    // instead, but we must run "hg diff" later anyway in most cases, so
    // building and caching it shouldn't hurt us.

    $diff = $this->getFullMercurialDiff();
    if (!$diff) {
      return array();
    }

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

    return $status_map;
  }

  protected function didReloadWorkingCopy() {
    // Diffs are against ".", so we need to drop the cache if we change the
    // working copy.
    $this->rawDiffCache = array();
    $this->branch = null;
  }

  private function getDiffOptions() {
    $options = array(
      '--git',
      '-U'.$this->getDiffLinesOfContext(),
    );
    return implode(' ', $options);
  }

  public function getRawDiffText($path) {
    $options = $this->getDiffOptions();

    $range = $this->getBaseCommit();

    $raw_diff_cache_key = $options.' '.$range.' '.$path;
    if (idx($this->rawDiffCache, $raw_diff_cache_key)) {
      return idx($this->rawDiffCache, $raw_diff_cache_key);
    }

    list($stdout) = $this->execxLocal(
      'diff %C --rev %s -- %s',
      $options,
      $range,
      $path);

    $this->rawDiffCache[$raw_diff_cache_key] = $stdout;

    return $stdout;
  }

  public function getFullMercurialDiff() {
    return $this->getRawDiffText('');
  }

  public function getOriginalFileData($path) {
    return $this->getFileDataAtRevision($path, $this->getBaseCommit());
  }

  public function getCurrentFileData($path) {
    return $this->getFileDataAtRevision(
      $path,
      $this->getWorkingCopyRevision());
  }

  public function getBulkOriginalFileData($paths) {
    return $this->getBulkFileDataAtRevision($paths, $this->getBaseCommit());
  }

  public function getBulkCurrentFileData($paths) {
    return $this->getBulkFileDataAtRevision(
      $paths,
      $this->getWorkingCopyRevision());
  }

  private function getBulkFileDataAtRevision($paths, $revision) {
    // Calling 'hg cat' on each file individually is slow (1 second per file
    // on a large repo) because mercurial has to decompress and parse the
    // entire manifest every time. Do it in one large batch instead.

    // hg cat will write the file data to files in a temp directory
    $tmpdir = Filesystem::createTemporaryDirectory();

    // Mercurial doesn't create the directories for us :(
    foreach ($paths as $path) {
      $tmppath = $tmpdir.'/'.$path;
      Filesystem::createDirectory(dirname($tmppath), 0755, true);
    }

    list($err, $stdout) = $this->execManualLocal(
      'cat --rev %s --output %s -- %C',
      $revision,
      // %p is the formatter for the repo-relative filepath
      $tmpdir.'/%p',
      implode(' ', $paths));

    $filedata = array();
    foreach ($paths as $path) {
      $tmppath = $tmpdir.'/'.$path;
      if (Filesystem::pathExists($tmppath)) {
        $filedata[$path] = Filesystem::readFile($tmppath);
      }
    }

    Filesystem::remove($tmpdir);

    return $filedata;
  }

  private function getFileDataAtRevision($path, $revision) {
    list($err, $stdout) = $this->execManualLocal(
      'cat --rev %s -- %s',
      $revision,
      $path);
    if ($err) {
      // Assume this is "no file at revision", i.e. a deleted or added file.
      return null;
    } else {
      return $stdout;
    }
  }

  public function getWorkingCopyRevision() {
    return '.';
  }

  public function isHistoryDefaultImmutable() {
    return true;
  }

  public function supportsAmend() {
    list($err, $stdout) = $this->execManualLocal('help commit');
    if ($err) {
      return false;
    } else {
      return (strpos($stdout, 'amend') !== false);
    }
  }

  public function supportsRebase() {
    if ($this->supportsRebase === null) {
      list($err) = $this->execManualLocal('help rebase');
      $this->supportsRebase = $err === 0;
    }

    return $this->supportsRebase;
  }

  public function supportsPhases() {
    if ($this->supportsPhases === null) {
      list($err) = $this->execManualLocal('help phase');
      $this->supportsPhases = $err === 0;
    }

    return $this->supportsPhases;
  }

  public function supportsCommitRanges() {
    return true;
  }

  public function supportsLocalCommits() {
    return true;
  }

  public function getAllBranches() {
    list($branch_info) = $this->execxLocal('bookmarks');
    if (trim($branch_info) == 'no bookmarks set') {
      return array();
    }

    $matches = null;
    preg_match_all(
      '/^\s*(\*?)\s*(.+)\s(\S+)$/m',
      $branch_info,
      $matches,
      PREG_SET_ORDER);

    $return = array();
    foreach ($matches as $match) {
      list(, $current, $name) = $match;
      $return[] = array(
        'current' => (bool)$current,
        'name'    => rtrim($name),
      );
    }
    return $return;
  }

  public function hasLocalCommit($commit) {
    try {
      $this->getCanonicalRevisionName($commit);
      return true;
    } catch (Exception $ex) {
      return false;
    }
  }

  public function getCommitMessage($commit) {
    list($message) = $this->execxLocal(
      'log --template={desc} --rev %s',
      $commit);
    return $message;
  }

  public function getAllLocalChanges() {
    $diff = $this->getFullMercurialDiff();
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
    if ($branch) {
      $err = phutil_passthru(
        '(cd %s && HGPLAIN=1 hg merge --rev %s && hg commit -m %s)',
        $this->getPath(),
        $branch,
        $message);
    } else {
      $err = phutil_passthru(
        '(cd %s && HGPLAIN=1 hg merge && hg commit -m %s)',
        $this->getPath(),
        $message);
    }

    if ($err) {
      throw new ArcanistUsageException(pht('Merge failed!'));
    }
  }

  public function getFinalizedRevisionMessage() {
    return pht(
      "You may now push this commit upstream, as appropriate (e.g. with ".
      "'%s' or by printing and faxing it).",
      'hg push');
  }

  public function getCommitMessageLog() {
    $base_commit = $this->getBaseCommit();
    list($stdout) = $this->execxLocal(
      'log --template %s --rev %s --branch %s --',
      "{node}\1{desc}\2",
      hgsprintf('(%s::. - %s)', $base_commit, $base_commit),
      $this->getBranchName());

    $map = array();

    $logs = explode("\2", trim($stdout));
    foreach (array_filter($logs) as $log) {
      list($node, $desc) = explode("\1", $log);
      $map[$node] = $desc;
    }

    return array_reverse($map);
  }

  public function loadWorkingCopyDifferentialRevisions(
    ConduitClient $conduit,
    array $query) {

    $messages = $this->getCommitMessageLog();
    $parser = new ArcanistDiffParser();

    // First, try to find revisions by explicit revision IDs in commit messages.
    $reason_map = array();
    $revision_ids = array();
    foreach ($messages as $node_id => $message) {
      $object = ArcanistDifferentialCommitMessage::newFromRawCorpus($message);

      if ($object->getRevisionID()) {
        $revision_ids[] = $object->getRevisionID();
        $reason_map[$object->getRevisionID()] = $node_id;
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
        $results[$key]['why'] =
          pht(
            "Commit message for '%s' has explicit 'Differential Revision'.",
            $hash);
      }

      return $results;
    }

    // Try to find revisions by hash.
    $hashes = array();
    foreach ($this->getLocalCommitInformation() as $commit) {
      $hashes[] = array('hgcm', $commit['commit']);
    }

    if ($hashes) {

      // NOTE: In the case of "arc diff . --uncommitted" in a Mercurial working
      // copy with dirty changes, there may be no local commits.

      $results = $conduit->callMethodSynchronous(
        'differential.query',
        $query + array(
          'commitHashes' => $hashes,
        ));

      foreach ($results as $key => $hash) {
        $results[$key]['why'] = pht(
          'A mercurial commit hash in the commit range is already attached '.
          'to the Differential revision.');
      }

      return $results;
    }

    return array();
  }

  public function updateWorkingCopy() {
    $this->execxLocal('up');
    $this->reloadWorkingCopy();
  }

  private function getMercurialConfig($key, $default = null) {
    list($stdout) = $this->execxLocal('showconfig %s', $key);
    if ($stdout == '') {
      return $default;
    }
    return rtrim($stdout);
  }

  public function getAuthor() {
    $full_author = $this->getMercurialConfig('ui.username');
    list($author, $author_email) = $this->parseFullAuthor($full_author);
    return $author;
  }

  /**
   * Parse the Mercurial author field.
   *
   * Not everyone enters their email address as a part of the username
   * field. Try to make it work when it's obvious.
   *
   * @param string $full_author
   * @return array
   */
  protected function parseFullAuthor($full_author) {
    if (strpos($full_author, '@') === false) {
      $author = $full_author;
      $author_email = null;
    } else {
      $email = new PhutilEmailAddress($full_author);
      $author = $email->getDisplayName();
      $author_email = $email->getAddress();
    }

    return array($author, $author_email);
  }

  public function addToCommit(array $paths) {
    $this->execxLocal(
      'addremove -- %Ls',
      $paths);
    $this->reloadWorkingCopy();
  }

  public function doCommit($message) {
    $tmp_file = new TempFile();
    Filesystem::writeFile($tmp_file, $message);
    $this->execxLocal('commit -l %s', $tmp_file);
    $this->reloadWorkingCopy();
  }

  public function amendCommit($message = null) {
    if ($message === null) {
      $message = $this->getCommitMessage('.');
    }

    $tmp_file = new TempFile();
    Filesystem::writeFile($tmp_file, $message);

    try {
      $this->execxLocal(
        'commit --amend -l %s',
        $tmp_file);
    } catch (CommandException $ex) {
      if (preg_match('/nothing changed/', $ex->getStdOut())) {
        // NOTE: Mercurial considers it an error to make a no-op amend. Although
        // we generally defer to the underlying VCS to dictate behavior, this
        // one seems a little goofy, and we use amend as part of various
        // workflows under the assumption that no-op amends are fine. If this
        // amend failed because it's a no-op, just continue.
      } else {
        throw $ex;
      }
    }

    $this->reloadWorkingCopy();
  }

  public function getCommitSummary($commit) {
    if ($commit == 'null') {
      return pht('(The Empty Void)');
    }

    list($summary) = $this->execxLocal(
      'log --template {desc} --limit 1 --rev %s',
      $commit);

    $summary = head(explode("\n", $summary));

    return trim($summary);
  }

  public function backoutCommit($commit_hash) {
    $this->execxLocal('backout -r %s', $commit_hash);
    $this->reloadWorkingCopy();
    if (!$this->getUncommittedStatus()) {
      throw new ArcanistUsageException(
        pht('%s has already been reverted.', $commit_hash));
    }
  }

  public function getBackoutMessage($commit_hash) {
    return pht('Backed out changeset %s,', $commit_hash);
  }

  public function resolveBaseCommitRule($rule, $source) {
    list($type, $name) = explode(':', $rule, 2);

    // NOTE: This function MUST return node hashes or symbolic commits (like
    // branch names or the word "tip"), not revsets. This includes ".^" and
    // similar, which a revset, not a symbolic commit identifier. If you return
    // a revset it will be escaped later and looked up literally.

    switch ($type) {
      case 'hg':
        $matches = null;
        if (preg_match('/^gca\((.+)\)$/', $name, $matches)) {
          list($err, $merge_base) = $this->execManualLocal(
            'log --template={node} --rev %s',
            sprintf('ancestor(., %s)', $matches[1]));
          if (!$err) {
            $this->setBaseCommitExplanation(
              pht(
                "it is the greatest common ancestor of '%s' and %s, as ".
                "specified by '%s' in your %s 'base' configuration.",
                $matches[1],
                '.',
                $rule,
                $source));
            return trim($merge_base);
          }
        } else {
          list($err, $commit) = $this->execManualLocal(
            'log --template {node} --rev %s',
            hgsprintf('%s', $name));

          if ($err) {
            list($err, $commit) = $this->execManualLocal(
              'log --template {node} --rev %s',
              $name);
          }
          if (!$err) {
            $this->setBaseCommitExplanation(
              pht(
                "it is specified by '%s' in your %s 'base' configuration.",
                $rule,
                $source));
            return trim($commit);
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
            return 'null';
          case 'outgoing':
            list($err, $outgoing_base) = $this->execManualLocal(
              'log --template={node} --rev %s',
              'limit(reverse(ancestors(.) - outgoing()), 1)');
            if (!$err) {
              $this->setBaseCommitExplanation(
                pht(
                  "it is the first ancestor of the working copy that is not ".
                  "outgoing, and it matched the rule %s in your %s ".
                  "'base' configuration.",
                  $rule,
                  $source));
              return trim($outgoing_base);
            }
          case 'amended':
            $text = $this->getCommitMessage('.');
            $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
              $text);
            if ($message->getRevisionID()) {
              $this->setBaseCommitExplanation(
                pht(
                  "'%s' has been amended with 'Differential Revision:', ".
                  "as specified by '%s' in your %s 'base' configuration.",
                  '.'.
                  $rule,
                  $source));
              // NOTE: This should be safe because Mercurial doesn't support
              // amend until 2.2.
              return $this->getCanonicalRevisionName('.^');
            }
            break;
          case 'bookmark':
            $revset =
              'limit('.
              '  sort('.
              '    (ancestors(.) and bookmark() - .) or'.
              '    (ancestors(.) - outgoing()), '.
              '  -rev),'.
              '1)';
            list($err, $bookmark_base) = $this->execManualLocal(
              'log --template={node} --rev %s',
              $revset);
            if (!$err) {
              $this->setBaseCommitExplanation(
                pht(
                  "it is the first ancestor of %s that either has a bookmark, ".
                  "or is already in the remote and it matched the rule %s in ".
                  "your %s 'base' configuration",
                  '.',
                  $rule,
                  $source));
              return trim($bookmark_base);
            }
            break;
          case 'this':
            $this->setBaseCommitExplanation(
              pht(
                "you specified '%s' in your %s 'base' configuration.",
                $rule,
                $source));
            return $this->getCanonicalRevisionName('.^');
          default:
            if (preg_match('/^nodiff\((.+)\)$/', $name, $matches)) {
              list($results) = $this->execxLocal(
                'log --template %s --rev %s',
                "{node}\1{desc}\2",
                sprintf('ancestor(.,%s)::.^', $matches[1]));
              $results = array_reverse(explode("\2", trim($results)));

              foreach ($results as $result) {
                if (empty($result)) {
                  continue;
                }

                list($node, $desc) = explode("\1", $result, 2);

                $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
                  $desc);
                if ($message->getRevisionID()) {
                  $this->setBaseCommitExplanation(
                    pht(
                      "it is the first ancestor of %s that has a diff and is ".
                      "the gca or a descendant of the gca with '%s', ".
                      "specified by '%s' in your %s 'base' configuration.",
                      '.',
                      $matches[1],
                      $rule,
                      $source));
                  return $node;
                }
              }
            }
            break;
          }
        break;
      default:
        return null;
    }

    return null;

  }

  public function isHgSubversionRepo() {
    return file_exists($this->getPath('.hg/svn/rev_map'));
  }

  public function getSubversionInfo() {
    $info = array();
    $base_path = null;
    $revision = null;
    list($err, $raw_info) = $this->execManualLocal('svn info');
    if (!$err) {
      foreach (explode("\n", trim($raw_info)) as $line) {
        list($key, $value) = explode(': ', $line, 2);
        switch ($key) {
          case 'URL':
            $info['base_path'] = $value;
            $base_path = $value;
            break;
          case 'Repository UUID':
            $info['uuid'] = $value;
            break;
          case 'Revision':
            $revision = $value;
            break;
          default:
            break;
        }
      }
      if ($base_path && $revision) {
        $info['base_revision'] = $base_path.'@'.$revision;
      }
    }
    return $info;
  }

  public function getActiveBookmark() {
    $bookmarks = $this->getBookmarks();
    foreach ($bookmarks as $bookmark) {
      if ($bookmark['is_active']) {
        return $bookmark['name'];
      }
    }

    return null;
  }

  public function isBookmark($name) {
    $bookmarks = $this->getBookmarks();
    foreach ($bookmarks as $bookmark) {
      if ($bookmark['name'] === $name) {
        return true;
      }
    }

    return false;
  }

  public function isBranch($name) {
    $branches = $this->getBranches();
    foreach ($branches as $branch) {
      if ($branch['name'] === $name) {
        return true;
      }
    }

    return false;
  }

  public function getBranches() {
    list($stdout) = $this->execxLocal('--debug branches');
    $lines = ArcanistMercurialParser::parseMercurialBranches($stdout);

    $branches = array();
    foreach ($lines as $name => $spec) {
      $branches[] = array(
        'name' => $name,
        'revision' => $spec['rev'],
      );
    }

    return $branches;
  }

  public function getBookmarks() {
    $bookmarks = array();

    list($raw_output) = $this->execxLocal('bookmarks');
    $raw_output = trim($raw_output);
    if ($raw_output !== 'no bookmarks set') {
      foreach (explode("\n", $raw_output) as $line) {
        // example line:  * mybook               2:6b274d49be97
        list($name, $revision) = $this->splitBranchOrBookmarkLine($line);

        $is_active = false;
        if ('*' === $name[0]) {
          $is_active = true;
          $name = substr($name, 2);
        }

        $bookmarks[] = array(
          'is_active' => $is_active,
          'name' => $name,
          'revision' => $revision,
        );
      }
    }

    return $bookmarks;
  }

  private function splitBranchOrBookmarkLine($line) {
    // branches and bookmarks are printed in the format:
    // default                 0:a5ead76cdf85 (inactive)
    // * mybook               2:6b274d49be97
    // this code divides the name half from the revision half
    // it does not parse the * and (inactive) bits
    $colon_index = strrpos($line, ':');
    $before_colon = substr($line, 0, $colon_index);
    $start_rev_index = strrpos($before_colon, ' ');
    $name = substr($line, 0, $start_rev_index);
    $rev = substr($line, $start_rev_index);

    return array(trim($name), trim($rev));
  }

  public function getRemoteURI() {
    list($stdout) = $this->execxLocal('paths default');

    $stdout = trim($stdout);
    if (strlen($stdout)) {
      return $stdout;
    }

    return null;
  }

}
