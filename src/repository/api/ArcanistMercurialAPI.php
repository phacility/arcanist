<?php

/**
 * Interfaces with the Mercurial working copies.
 */
final class ArcanistMercurialAPI extends ArcanistRepositoryAPI {

  /**
   * Mercurial deceptively indicates that the default encoding is UTF-8 however
   * however the actual default appears to be "something else", at least on
   * Windows systems. Force all mercurial commands to use UTF-8 encoding.
   */
  const ROOT_HG_COMMAND = 'hg --encoding utf-8 ';

  private $branch;
  private $localCommitInfo;
  private $rawDiffCache = array();

  private $featureResults = array();
  private $featureFutures = array();

  protected function buildLocalFuture(array $argv) {
    $argv[0] = self::ROOT_HG_COMMAND.$argv[0];

    return $this->newConfiguredFuture(newv('ExecFuture', $argv));
  }

  public function newPassthru($pattern /* , ... */) {
    $args = func_get_args();
    $args[0] = self::ROOT_HG_COMMAND.$args[0];

    return $this->newConfiguredFuture(newv('PhutilExecPassthru', $args));
  }

  private function newConfiguredFuture(PhutilExecutableFuture $future) {
    $args = func_get_args();

    $env = $this->getMercurialEnvironmentVariables();

    return $future
      ->setEnv($env)
      ->setCWD($this->getPath());
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
    list($stdout) = $this->execxLocal(
      'log -l 1 --template %s -r %s --',
      '{node}',
      $string);

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

    list($err, $stdout) = $this->execManualLocal(
      'log --branch %s -r %s --style default',
      $this->getBranchName(),
      'draft()');

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
      if (!($mask & parent::FLAG_UNTRACKED)) {
        // Mark tracked files as uncommitted.
        $mask |= self::FLAG_UNCOMMITTED;
      }

      $results[$path] |= $mask;
    }

    return $results->toArray();
  }

  protected function buildCommitRangeStatus() {
    list($stdout) = $this->execxLocal(
      'status --rev %s --rev tip',
      $this->getBaseCommit());

    $results = new PhutilArrayWithDefaultValue();

    $working_status = ArcanistMercurialParser::parseMercurialStatus($stdout);
    foreach ($working_status as $path => $mask) {
      $results[$path] |= $mask;
    }

    return $results->toArray();
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

    // NOTE: The "%s%%p" construction passes a literal "%p" to Mercurial,
    // which is a formatting directive for a repo-relative filepath. The
    // particulars of the construction avoid Windows escaping issues. See
    // PHI904.

    list($err, $stdout) = $this->execManualLocal(
      'cat --rev %s --output %s%%p -- %Ls',
      $revision,
      $tmpdir.DIRECTORY_SEPARATOR,
      $paths);

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

  protected function newCurrentCommitSymbol() {
    return $this->getWorkingCopyRevision();
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

  public function supportsCommitRanges() {
    return true;
  }

  public function supportsLocalCommits() {
    return true;
  }

  public function getBaseCommitRef() {
    $base_commit = $this->getBaseCommit();

    if ($base_commit === 'null') {
      return null;
    }

    $base_message = $this->getCommitMessage($base_commit);

    return $this->newCommitRef()
      ->setCommitHash($base_commit)
      ->attachMessage($base_message);
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
    $this->execxLocal('commit --logfile %s', $tmp_file);
    $this->reloadWorkingCopy();
  }

  public function amendCommit($message = null) {
    $path_statuses = $this->buildUncommittedStatus();

    $existing_message = $this->getCommitMessage(
      $this->getWorkingCopyRevision());

    if ($message === null || $message == $existing_message) {
      if (empty($path_statuses)) {
        // If there are no changes to the working directory and the message is
        // not being changed then there's nothing to amend. Notably Mercurial
        // will return an error code if trying to amend a commit with no change
        // to the commit metadata or file changes.
        return;
      }

      $message = $this->getCommitMessage('.');
    }

    $tmp_file = new TempFile();
    Filesystem::writeFile($tmp_file, $message);

    if ($this->getMercurialFeature('evolve')) {
      $this->execxLocal('amend --logfile %s --', $tmp_file);
      try {
        $this->execxLocal('evolve --all --');
      } catch (CommandException $ex) {
        $this->execxLocal('evolve --abort --');
        throw $ex;
      }
      $this->reloadWorkingCopy();
      return;
    }

    // Get the child nodes of the current changeset.
    list($children) = $this->execxLocal(
      'log --template %s --rev %s --',
      '{node} ',
      'children(.)');
    $child_nodes = array_filter(explode(' ', $children));

    // For a head commit we can simply use `commit --amend` for both new commit
    // message and amending changes from the working directory.
    if (empty($child_nodes)) {
        $this->execxLocal('commit --amend --logfile %s --', $tmp_file);
    } else {
      $this->amendNonHeadCommit($child_nodes, $tmp_file);
    }

    $this->reloadWorkingCopy();
  }

  /**
   * Amends a non-head commit with a new message and file changes. This
   * strategy is for Mercurial repositories without the evolve extension.
   *
   * 1. Run 'arc-amend' which uses Mercurial internals to amend the current
   *    commit with updated message/file-changes. It results in a new commit
   *    from the right parent
   * 2. For each branch from the original commit, rebase onto the new commit,
   *    removing the original branch. Note that there is potential for this to
   *    cause a conflict but this is something the user has to address.
   * 3. Strip the original commit.
   *
   * @param array     The list of child changesets off the original commit.
   * @param file      The file containing the new commit message.
   */
  private function amendNonHeadCommit($child_nodes, $tmp_file) {
    list($current) = $this->execxLocal(
      'log --template %s --rev . --',
      '{node}');

    $this->execxLocalWithExtension(
      'arc-hg',
      'arc-amend --logfile %s',
      $tmp_file);

    list($new_commit) = $this->execxLocal(
      'log --rev tip --template %s --',
      '{node}');

    try {
      $rebase_args = array(
        '--dest',
        $new_commit,
      );
      foreach ($child_nodes as $child) {
        $rebase_args[] = '--source';
        $rebase_args[] = $child;
      }

      $this->execxLocalWithExtension(
        'rebase',
        'rebase %Ls --',
        $rebase_args);
    } catch (CommandException $ex) {
      $this->execxLocalWithExtension(
        'rebase',
        'rebase --abort --');
      throw $ex;
    }

    $this->execxLocalWithExtension(
      'strip',
      'strip --rev %s --',
      $current);
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
                  '.',
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
    $bookmark = $this->newMarkerRefQuery()
      ->withMarkerTypes(
        array(
          ArcanistMarkerRef::TYPE_BOOKMARK,
        ))
      ->withIsActive(true)
      ->executeOne();

    if (!$bookmark) {
      return null;
    }

    return $bookmark->getName();
  }

  public function getRemoteURI() {
    // TODO: Remove this method in favor of RemoteRefQuery.

    list($stdout) = $this->execxLocal('paths default');

    $stdout = trim($stdout);
    if (strlen($stdout)) {
      return $stdout;
    }

    return null;
  }

  private function getMercurialEnvironmentVariables() {
    $env = array();

    // Mercurial has a "defaults" feature which basically breaks automation by
    // allowing the user to add random flags to any command. This feature is
    // "deprecated" and "a bad idea" that you should "forget ... existed"
    // according to project lead Matt Mackall:
    //
    //  http://markmail.org/message/hl3d6eprubmkkqh5
    //
    // There is an HGPLAIN environmental variable which enables "plain mode"
    // and hopefully disables this stuff.

    $env['HGPLAIN'] = 1;

    return $env;
  }

  protected function newLandEngine() {
    return new ArcanistMercurialLandEngine();
  }

  protected function newWorkEngine() {
    return new ArcanistMercurialWorkEngine();
  }

  public function newLocalState() {
    return id(new ArcanistMercurialLocalState())
      ->setRepositoryAPI($this);
  }

  public function willTestMercurialFeature($feature) {
    $this->executeMercurialFeatureTest($feature, false);
    return $this;
  }

  public function getMercurialFeature($feature) {
    return $this->executeMercurialFeatureTest($feature, true);
  }

  /**
   * Returns the necessary flag for using a Mercurial extension. This will
   * enable Mercurial built-in extensions and the "arc-hg" extension that is
   * included with Arcanist. This will not enable other extensions, e.g.
   * "evolve".
   *
   * @param string  The name of the extension to enable.
   * @return string  A new command pattern that includes the necessary flags to
   *                 enable the specified extension.
   */
  private function getMercurialExtensionFlag($extension) {
    switch ($extension) {
      case 'arc-hg':
        $path = phutil_get_library_root('arcanist');
        $path = dirname($path);
        $path = $path.'/support/hg/arc-hg.py';
        $ext_config = 'extensions.arc-hg='.$path;
        break;
      case 'rebase':
        $ext_config = 'extensions.rebase=';
        break;
      case 'shelve':
        $ext_config = 'extensions.shelve=';
        break;
      case 'strip':
        $ext_config = 'extensions.strip=';
        break;
      default:
        throw new Exception(
          pht('Unknown Mercurial Extension: "%s".', $extension));
    }

    return csprintf('--config %s', $ext_config);
  }

  /**
   * Produces the arguments that should be passed to Mercurial command
   * execution that enables a desired extension.
   *
   * @param string  The name of the extension to enable.
   * @param string  The command pattern that will be run with the extension
   *                enabled.
   * @param array   Parameters for the command pattern argument.
   * @return array  An array where the first item is a Mercurial command
   *                pattern that includes the necessary flag for enabling the
   *                desired extension, and all remaining items are parameters
   *                to that command pattern.
   */
  private function buildMercurialExtensionCommand(
    $extension,
    $pattern /* , ... */) {

    $args = func_get_args();

    $pattern_args = array_slice($args, 2);

    $ext_flag = $this->getMercurialExtensionFlag($extension);

    $full_cmd = $ext_flag.' '.$pattern;

    $args = array_merge(
      array($full_cmd),
      $pattern_args);

    return $args;
  }

  public function execxLocalWithExtension(
    $extension,
    $pattern /* , ... */) {

    $args = func_get_args();
    $extended_args = call_user_func_array(
      array($this, 'buildMercurialExtensionCommand'),
      $args);

    return call_user_func_array(
      array($this, 'execxLocal'),
      $extended_args);
  }

  public function execFutureLocalWithExtension(
    $extension,
    $pattern /* , ... */) {

    $args = func_get_args();
    $extended_args = call_user_func_array(
      array($this, 'buildMercurialExtensionCommand'),
      $args);

    return call_user_func_array(
      array($this, 'execFutureLocal'),
      $extended_args);
  }

  public function execPassthruWithExtension(
    $extension,
    $pattern /* , ... */) {

    $args = func_get_args();
    $extended_args = call_user_func_array(
      array($this, 'buildMercurialExtensionCommand'),
      $args);

    return call_user_func_array(
      array($this, 'execPassthru'),
      $extended_args);
  }

  public function execManualLocalWithExtension(
    $extension,
    $pattern /* , ... */) {

    $args = func_get_args();
    $extended_args = call_user_func_array(
      array($this, 'buildMercurialExtensionCommand'),
      $args);

    return call_user_func_array(
      array($this, 'execManualLocal'),
      $extended_args);
  }

  private function executeMercurialFeatureTest($feature, $resolve) {
    if (array_key_exists($feature, $this->featureResults)) {
      return $this->featureResults[$feature];
    }

    if (!array_key_exists($feature, $this->featureFutures)) {
      $future = $this->newMercurialFeatureFuture($feature);
      $future->start();
      $this->featureFutures[$feature] = $future;
    }

    if (!$resolve) {
      return;
    }

    $future = $this->featureFutures[$feature];
    $result = $this->resolveMercurialFeatureFuture($feature, $future);
    $this->featureResults[$feature] = $result;

    return $result;
  }

  private function newMercurialFeatureFuture($feature) {
    switch ($feature) {
      case 'shelve':
        return $this->execFutureLocalWithExtension(
          'shelve',
          'shelve --help --');
      case 'evolve':
        return $this->execFutureLocal('prune --help --');
      default:
        throw new Exception(
          pht(
            'Unknown Mercurial feature "%s".',
            $feature));
    }
  }

  private function resolveMercurialFeatureFuture($feature, $future) {
    // By default, assume the feature is a simple capability test and the
    // capability is present if the feature resolves without an error.

    list($err) = $future->resolve();
    return !$err;
  }

  protected function newSupportedMarkerTypes() {
    return array(
      ArcanistMarkerRef::TYPE_BRANCH,
      ArcanistMarkerRef::TYPE_BOOKMARK,
    );
  }

  protected function newMarkerRefQueryTemplate() {
    return new ArcanistMercurialRepositoryMarkerQuery();
  }

  protected function newRemoteRefQueryTemplate() {
    return new ArcanistMercurialRepositoryRemoteQuery();
  }

  protected function newNormalizedURI($uri) {
    return new ArcanistRepositoryURINormalizer(
      ArcanistRepositoryURINormalizer::TYPE_MERCURIAL,
      $uri);
  }

  protected function newCommitGraphQueryTemplate() {
    return new ArcanistMercurialCommitGraphQuery();
  }

  protected function newPublishedCommitHashes() {
    $future = $this->newFuture(
      'log --rev %s --template %s',
      hgsprintf('parents(draft()) - draft()'),
      '{node}\n');
    list($lines) = $future->resolve();

    $lines = phutil_split_lines($lines, false);

    $hashes = array();
    foreach ($lines as $line) {
      if (!strlen(trim($line))) {
        continue;
      }
      $hashes[] = $line;
    }

    return $hashes;
  }

}
