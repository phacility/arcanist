<?php

/**
 * Interfaces with Subversion working copies.
 */
final class ArcanistSubversionAPI extends ArcanistRepositoryAPI {

  protected $svnStatus;
  protected $svnBaseRevisions;
  protected $svnInfo = array();

  protected $svnInfoRaw = array();
  protected $svnDiffRaw = array();

  private $svnBaseRevisionNumber;
  private $statusPaths = array();

  public function getSourceControlSystemName() {
    return 'svn';
  }

  public function getMetadataPath() {
    static $svn_dir = null;
    if ($svn_dir === null) {
      // from svn 1.7, subversion keeps a single .svn directly under
      // the working copy root. However, we allow .arcconfigs that
      // aren't at the working copy root.
      foreach (Filesystem::walkToRoot($this->getPath()) as $parent) {
        $possible_svn_dir = Filesystem::resolvePath('.svn', $parent);
        if (Filesystem::pathExists($possible_svn_dir)) {
          $svn_dir = $possible_svn_dir;
          break;
        }
      }
    }
    return $svn_dir;
  }

  protected function buildLocalFuture(array $argv) {
    $argv[0] = 'svn '.$argv[0];

    $future = newv('ExecFuture', $argv);
    $future->setCWD($this->getPath());
    return $future;
  }

  protected function buildCommitRangeStatus() {
    // In SVN, there are never any previous commits in the range -- it is all in
    // the uncommitted status.
    return array();
  }

  protected function buildUncommittedStatus() {
    return $this->getSVNStatus();
  }

  public function getSVNBaseRevisions() {
    if ($this->svnBaseRevisions === null) {
      $this->getSVNStatus();
    }
    return $this->svnBaseRevisions;
  }

  public function limitStatusToPaths(array $paths) {
    $this->statusPaths = $paths;
    return $this;
  }

  public function getSVNStatus($with_externals = false) {
    if ($this->svnStatus === null) {
      if ($this->statusPaths) {
        list($status) = $this->execxLocal(
          '--xml status %Ls',
          $this->statusPaths);
      } else {
        list($status) = $this->execxLocal('--xml status');
      }
      $xml = new SimpleXMLElement($status);

      $externals = array();
      $files = array();

      foreach ($xml->target as $target) {
        $this->svnBaseRevisions = array();
        foreach ($target->entry as $entry) {
          $path = (string)$entry['path'];
          // On Windows, we get paths with backslash directory separators here.
          // Normalize them to the format everything else expects and generates.
          if (phutil_is_windows()) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
          }
          $mask = 0;

          $props = (string)($entry->{'wc-status'}[0]['props']);
          $item  = (string)($entry->{'wc-status'}[0]['item']);

          $base = (string)($entry->{'wc-status'}[0]['revision']);
          $this->svnBaseRevisions[$path] = $base;

          switch ($props) {
            case 'none':
            case 'normal':
              break;
            case 'modified':
              $mask |= self::FLAG_MODIFIED;
              break;
            default:
              throw new Exception(pht(
                "Unrecognized property status '%s'.",
                $props));
          }

          $mask |= $this->parseSVNStatus($item);
          if ($item == 'external') {
            $externals[] = $path;
          }

          // This is new in or around Subversion 1.6.
          $tree_conflicts = ($entry->{'wc-status'}[0]['tree-conflicted']);
          if ((string)$tree_conflicts) {
            $mask |= self::FLAG_CONFLICT;
          }

          $files[$path] = $mask;
        }
      }

      foreach ($files as $path => $mask) {
        foreach ($externals as $external) {
          if (!strncmp($path.'/', $external.'/', strlen($external) + 1)) {
            $files[$path] |= self::FLAG_EXTERNALS;
          }
        }
      }

      $this->svnStatus = $files;
    }

    $status = $this->svnStatus;
    if (!$with_externals) {
      foreach ($status as $path => $mask) {
        if ($mask & parent::FLAG_EXTERNALS) {
          unset($status[$path]);
        }
      }
    }

    return $status;
  }

  private function parseSVNStatus($item) {
    switch ($item) {
      case 'none':
        // We can get 'none' for property changes on a directory.
      case 'normal':
        return 0;
      case 'external':
        return self::FLAG_EXTERNALS;
      case 'unversioned':
        return self::FLAG_UNTRACKED;
      case 'obstructed':
        return self::FLAG_OBSTRUCTED;
      case 'missing':
        return self::FLAG_MISSING;
      case 'added':
        return self::FLAG_ADDED;
      case 'replaced':
        // This is the result of "svn rm"-ing a file, putting another one
        // in place of it, and then "svn add"-ing the new file. Just treat
        // this as equivalent to "modified".
        return self::FLAG_MODIFIED;
      case 'modified':
        return self::FLAG_MODIFIED;
      case 'deleted':
        return self::FLAG_DELETED;
      case 'conflicted':
        return self::FLAG_CONFLICT;
      case 'incomplete':
        return self::FLAG_INCOMPLETE;
      default:
        throw new Exception(pht("Unrecognized item status '%s'.", $item));
    }
  }

  public function addToCommit(array $paths) {
    $add = array_filter($paths, 'Filesystem::pathExists');
    if ($add) {
      $this->execxLocal(
        'add -- %Ls',
        $add);
    }
    if ($add != $paths) {
      $this->execxLocal(
        'delete -- %Ls',
        array_diff($paths, $add));
    }
    $this->svnStatus = null;
  }

  public function getSVNProperty($path, $property) {
    list($stdout) = execx(
      'svn propget %s %s@',
      $property,
      $this->getPath($path));
    return trim($stdout);
  }

  public function getSourceControlPath() {
    return idx($this->getSVNInfo('/'), 'URL');
  }

  public function getSourceControlBaseRevision() {
    $info = $this->getSVNInfo('/');
    return $info['URL'].'@'.$this->getSVNBaseRevisionNumber();
  }

  public function getCanonicalRevisionName($string) {
    // TODO: This could be more accurate, but is only used by `arc browse`
    // for now.

    if (is_numeric($string)) {
      return $string;
    }
    return null;
  }

  public function getSVNBaseRevisionNumber() {
    if ($this->svnBaseRevisionNumber) {
      return $this->svnBaseRevisionNumber;
    }
    $info = $this->getSVNInfo('/');
    return $info['Revision'];
  }

  public function overrideSVNBaseRevisionNumber($effective_base_revision) {
    $this->svnBaseRevisionNumber = $effective_base_revision;
    return $this;
  }

  public function getBranchName() {
    $info = $this->getSVNInfo('/');
    $repo_root = idx($info, 'Repository Root');
    $repo_root_length = strlen($repo_root);
    $url = idx($info, 'URL');
    if (substr($url, 0, $repo_root_length) == $repo_root) {
      return substr($url, $repo_root_length);
    }
    return 'svn';
  }

  public function getRemoteURI() {
    return idx($this->getSVNInfo('/'), 'Repository Root');
  }

  public function buildInfoFuture($path) {
    if ($path == '/') {
      // When the root of a working copy is referenced by a symlink and you
      // execute 'svn info' on that symlink, svn fails. This is a longstanding
      // bug in svn:
      //
      // See http://subversion.tigris.org/issues/show_bug.cgi?id=2305
      //
      // To reproduce, do:
      //
      //  $ ln -s working_copy working_link
      //  $ svn info working_copy # ok
      //  $ svn info working_link # fails
      //
      // Work around this by cd-ing into the directory before executing
      // 'svn info'.
      return $this->buildLocalFuture(array('info .'));
    } else {
      // Note: here and elsewhere we need to append "@" to the path because if
      // a file has a literal "@" in it, everything after that will be
      // interpreted as a revision. By appending "@" with no argument, SVN
      // parses it properly.
      return $this->buildLocalFuture(array('info %s@', $this->getPath($path)));
    }
  }

  public function buildDiffFuture($path) {
    $root = phutil_get_library_root('arcanist');

    // The "--depth empty" flag prevents us from picking up changes in
    // children when we run 'diff' against a directory. Specifically, when a
    // user has added or modified some directory "example/", we want to return
    // ONLY changes to that directory when given it as a path. If we run
    // without "--depth empty", svn will give us changes to the directory
    // itself (such as property changes) and also give us changes to any
    // files within the directory (basically, implicit recursion). We don't
    // want that, so prevent recursive diffing. This flag does not work if the
    // directory is newly added (see T5555) so we need to filter the results
    // out later as well.

    if (phutil_is_windows()) {
      // TODO: Provide a binary_safe_diff script for Windows.
      // TODO: Provide a diff command which can take lines of context somehow.
      return $this->buildLocalFuture(
        array(
          'diff --depth empty %s',
          $path,
        ));
    } else {
      $diff_bin = $root.'/../scripts/repository/binary_safe_diff.sh';
      $diff_cmd = Filesystem::resolvePath($diff_bin);
      return $this->buildLocalFuture(
        array(
          'diff --depth empty --diff-cmd %s -x -U%d %s',
          $diff_cmd,
          $this->getDiffLinesOfContext(),
          $path,
        ));
    }
  }

  public function primeSVNInfoResult($path, $result) {
    $this->svnInfoRaw[$path] = $result;
    return $this;
  }

  public function primeSVNDiffResult($path, $result) {
    $this->svnDiffRaw[$path] = $result;
    return $this;
  }

  public function getSVNInfo($path) {
    if (empty($this->svnInfo[$path])) {

      if (empty($this->svnInfoRaw[$path])) {
        $this->svnInfoRaw[$path] = $this->buildInfoFuture($path)->resolve();
      }

      list($err, $stdout) = $this->svnInfoRaw[$path];
      if ($err) {
        throw new Exception(
          pht("Error #%d executing svn info against '%s'.", $err, $path));
      }

      // TODO: Hack for Windows.
      $stdout = str_replace("\r\n", "\n", $stdout);

      $patterns = array(
        '/^(URL): (\S+)$/m',
        '/^(Revision): (\d+)$/m',
        '/^(Last Changed Author): (\S+)$/m',
        '/^(Last Changed Rev): (\d+)$/m',
        '/^(Last Changed Date): (.+) \(.+\)$/m',
        '/^(Copied From URL): (\S+)$/m',
        '/^(Copied From Rev): (\d+)$/m',
        '/^(Repository Root): (\S+)$/m',
        '/^(Repository UUID): (\S+)$/m',
        '/^(Node Kind): (\S+)$/m',
      );

      $result = array();
      foreach ($patterns as $pattern) {
        $matches = null;
        if (preg_match($pattern, $stdout, $matches)) {
          $result[$matches[1]] = $matches[2];
        }
      }

      if (isset($result['Last Changed Date'])) {
        $result['Last Changed Date'] = strtotime($result['Last Changed Date']);
      }

      if (empty($result)) {
        throw new Exception(pht('Unable to parse SVN info.'));
      }

      $this->svnInfo[$path] = $result;
    }

    return $this->svnInfo[$path];
  }


  public function getRawDiffText($path) {
    $status = $this->getSVNStatus();
    if (!isset($status[$path])) {
      return null;
    }

    $status = $status[$path];

    // Build meaningful diff text for "svn copy" operations.
    if ($status & parent::FLAG_ADDED) {
      $info = $this->getSVNInfo($path);
      if (!empty($info['Copied From URL'])) {
        return $this->buildSyntheticAdditionDiff(
          $path,
          $info['Copied From URL'],
          $info['Copied From Rev']);
      }
    }

    // If we run "diff" on a binary file which doesn't have the "svn:mime-type"
    // of "application/octet-stream", `diff' will explode in a rain of
    // unhelpful hellfire as it tries to build a textual diff of the two
    // files. We just fix this inline since it's pretty unambiguous.
    // TODO: Move this to configuration?
    $matches = null;
    if (preg_match('/\.(gif|png|jpe?g|swf|pdf|ico)$/i', $path, $matches)) {
      // Check if the file is deleted first; SVN will complain if we try to
      // get properties of a deleted file.
      if ($status & parent::FLAG_DELETED) {
        return <<<EODIFF
Index: {$path}
===================================================================
Cannot display: file marked as a binary type.
svn:mime-type = application/octet-stream

EODIFF;
      }

      $mime = $this->getSVNProperty($path, 'svn:mime-type');
      if ($mime != 'application/octet-stream') {
        execx(
          'svn propset svn:mime-type application/octet-stream %s',
          self::escapeFileNameForSVN($this->getPath($path)));
      }
    }

    if (empty($this->svnDiffRaw[$path])) {
      $this->svnDiffRaw[$path] = $this->buildDiffFuture($path)->resolve();
    }

    list($err, $stdout, $stderr) = $this->svnDiffRaw[$path];

    // Note: GNU Diff returns 2 when SVN hands it binary files to diff and they
    // differ. This is not an error; it is documented behavior. But SVN isn't
    // happy about it. SVN will exit with code 1 and return the string below.
    if ($err != 0 && $stderr !== "svn: 'diff' returned 2\n") {
      throw new Exception(
        pht(
          "%s returned unexpected error code: %d\nstdout: %s\nstderr: %s",
          'svn diff',
          $err,
          $stdout,
          $stderr));
    }

    if ($err == 0 && empty($stdout)) {
      // If there are no changes, 'diff' exits with no output, but that means
      // we can not distinguish between empty and unmodified files. Build a
      // synthetic "diff" without any changes in it.
      return $this->buildSyntheticUnchangedDiff($path);
    }

    return $stdout;
  }

  protected function buildSyntheticAdditionDiff($path, $source, $rev) {
    if (is_dir($this->getPath($path))) {
      return null;
    }

    $type = $this->getSVNProperty($path, 'svn:mime-type');
    if ($type == 'application/octet-stream') {
      return <<<EODIFF
Index: {$path}
===================================================================
Cannot display: file marked as a binary type.
svn:mime-type = application/octet-stream

EODIFF;
    }

    $data = Filesystem::readFile($this->getPath($path));
    list($orig) = execx('svn cat %s@%s', $source, $rev);

    $src = new TempFile();
    $dst = new TempFile();
    Filesystem::writeFile($src, $orig);
    Filesystem::writeFile($dst, $data);

    list($err, $diff) = exec_manual(
      'diff -L a/%s -L b/%s -U%d %s %s',
      str_replace($this->getSourceControlPath().'/', '', $source),
      $path,
      $this->getDiffLinesOfContext(),
      $src,
      $dst);

    if ($err == 1) { // 1 means there are differences.
      return <<<EODIFF
Index: {$path}
===================================================================
{$diff}

EODIFF;
    } else {
      return $this->buildSyntheticUnchangedDiff($path);
    }
  }

  protected function buildSyntheticUnchangedDiff($path) {
    $full_path = $this->getPath($path);
    if (is_dir($full_path)) {
      return null;
    }

    if (!file_exists($full_path)) {
      return null;
    }

    $data = Filesystem::readFile($full_path);
    $lines = explode("\n", $data);
    $len = count($lines);
    foreach ($lines as $key => $line) {
      $lines[$key] = ' '.$line;
    }
    $lines = implode("\n", $lines);
    return <<<EODIFF
Index: {$path}
===================================================================
--- {$path} (synthetic)
+++ {$path} (synthetic)
@@ -1,{$len} +1,{$len} @@
{$lines}

EODIFF;
  }

  public function getAllFiles() {
    // TODO: Handle paths with newlines.
    $future = $this->buildLocalFuture(array('list -R'));
    return new PhutilCallbackFilterIterator(
      new LinesOfALargeExecFuture($future),
      array($this, 'filterFiles'));
  }

  public function getChangedFiles($since_commit) {
    $url = '';
    $match = null;
    if (preg_match('/(.*)@(.*)/', $since_commit, $match)) {
      list(, $url, $since_commit) = $match;
    }
    // TODO: Handle paths with newlines.
    list($stdout) = $this->execxLocal(
      '--xml diff --revision %s:HEAD --summarize %s',
      $since_commit,
      $url);
    $xml = new SimpleXMLElement($stdout);

    $return = array();
    foreach ($xml->paths[0]->path as $path) {
      $return[(string)$path] = $this->parseSVNStatus($path['item']);
    }
    return $return;
  }

  public function filterFiles($path) {
    // NOTE: SVN uses '/' also on Windows.
    if ($path == '' || substr($path, -1) == '/') {
      return null;
    }
    return $path;
  }

  public function getBlame($path) {
    $blame = array();

    list($stdout) = $this->execxLocal('blame %s', $path);

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      // Empty file.
      return $blame;
    }

    foreach (explode("\n", $stdout) as $line) {
      $m = array();
      if (!preg_match('/^\s*(\d+)\s+(\S+)/', $line, $m)) {
        throw new Exception(pht("Bad blame? `%s'", $line));
      }
      $revision = $m[1];
      $author = $m[2];
      $blame[] = array($author, $revision);
    }

    return $blame;
  }

  public function getOriginalFileData($path) {
    // SVN issues warnings for nonexistent paths, directories, etc., but still
    // returns no error code. However, for new paths in the working copy it
    // fails. Assume that failure means the original file does not exist.
    list($err, $stdout) = $this->execManualLocal('cat %s@', $path);
    if ($err) {
      return null;
    }
    return $stdout;
  }

  public function getCurrentFileData($path) {
    $full_path = $this->getPath($path);
    if (Filesystem::pathExists($full_path)) {
      return Filesystem::readFile($full_path);
    }
    return null;
  }

  public function getRepositoryUUID() {
    $info = $this->getSVNInfo('/');
    return $info['Repository UUID'];
  }

  public function getLocalCommitInformation() {
    return null;
  }

  public function isHistoryDefaultImmutable() {
    return true;
  }

  public function supportsAmend() {
    return false;
  }

  public function supportsCommitRanges() {
    return false;
  }

  public function supportsLocalCommits() {
    return false;
  }

  public function hasLocalCommit($commit) {
    return false;
  }

  public function getWorkingCopyRevision() {
    return $this->getSourceControlBaseRevision();
  }

  public function getFinalizedRevisionMessage() {
    // In other VCSes we give push instructions here, but it never makes sense
    // in SVN.
    return 'Done.';
  }

  public function loadWorkingCopyDifferentialRevisions(
    ConduitClient $conduit,
    array $query) {

    $results = $conduit->callMethodSynchronous('differential.query', $query);

    foreach ($results as $key => $result) {
      if (idx($result, 'sourcePath') != $this->getPath()) {
        unset($results[$key]);
      }
    }

    foreach ($results as $key => $result) {
      $results[$key]['why'] = pht('Matching working copy directory path.');
    }

    return $results;
  }

  public function updateWorkingCopy() {
    $this->execxLocal('up');
  }

  public static function escapeFileNamesForSVN(array $files) {
    foreach ($files as $k => $file) {
      $files[$k] = self::escapeFileNameForSVN($file);
    }
    return $files;
  }

  public static function escapeFileNameForSVN($file) {
    // SVN interprets "x@1" as meaning "file x at revision 1", which is not
    // intended for files named "sprite@2x.png" or similar. For files with an
    // "@" in their names, escape them by adding "@" at the end, which SVN
    // interprets as "at the working copy revision". There is a special case
    // where ".@" means "fail with an error" instead of ". at the working copy
    // revision", so avoid escaping "." into ".@".

    if (strpos($file, '@') !== false) {
      $file = $file.'@';
    }

    return $file;
  }

}
