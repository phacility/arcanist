<?php

/**
 * Converts changesets between different formats.
 */
final class ArcanistBundle extends Phobject {

  private $changes;
  private $conduit;
  private $blobs = array();
  private $diskPath;
  private $baseRevision;
  private $revisionID;
  private $encoding;
  private $loadFileDataCallback;
  private $authorName;
  private $authorEmail;
  private $byteLimit;
  private $reservedBytes;

  public function setAuthorEmail($author_email) {
    $this->authorEmail = $author_email;
    return $this;
  }

  public function getAuthorEmail() {
    return $this->authorEmail;
  }

  public function setAuthorName($author_name) {
    $this->authorName = $author_name;
    return $this;
  }

  public function getAuthorName() {
    return $this->authorName;
  }

  public function getFullAuthor() {
    $author_name = $this->getAuthorName();
    if ($author_name === null) {
      return null;
    }

    $author_email = $this->getAuthorEmail();
    if ($author_email === null) {
      return null;
    }

    $full_author = sprintf('%s <%s>', $author_name, $author_email);

    // Because git is very picky about the author being in a valid format,
    // verify that we can parse it.
    $address = new PhutilEmailAddress($full_author);
    if (!$address->getDisplayName() || !$address->getAddress()) {
      return null;
    }

    return $full_author;
  }

  public function setConduit(ConduitClient $conduit) {
    $this->conduit = $conduit;
    return $this;
  }

  public function setBaseRevision($base_revision) {
    $this->baseRevision = $base_revision;
    return $this;
  }

  public function setEncoding($encoding) {
    $this->encoding = $encoding;
    return $this;
  }

  public function getEncoding() {
    return $this->encoding;
  }

  public function setByteLimit($byte_limit) {
    $this->byteLimit = $byte_limit;
    return $this;
  }

  public function getByteLimit() {
    return $this->byteLimit;
  }

  public function getBaseRevision() {
    return $this->baseRevision;
  }

  public function setRevisionID($revision_id) {
    $this->revisionID = $revision_id;
    return $this;
  }

  public function getRevisionID() {
    return $this->revisionID;
  }

  public static function newFromChanges(array $changes) {
    $obj = new ArcanistBundle();
    $obj->changes = $changes;
    return $obj;
  }

  private function getEOL($patch_type) {
    // NOTE: Git always generates "\n" line endings, even under Windows, and
    // can not parse certain patches with "\r\n" line endings. SVN generates
    // patches with "\n" line endings on Mac or Linux and "\r\n" line endings
    // on Windows. (This EOL style is used only for patch metadata lines, not
    // for the actual patch content.)

    // (On Windows, Mercurial generates \n newlines for `--git` diffs, as it
    // must, but also \n newlines for unified diffs. We never need to deal with
    // these as we use Git format for Mercurial, so this case is currently
    // ignored.)

    switch ($patch_type) {
      case 'git':
        return "\n";
      case 'unified':
        return phutil_is_windows() ? "\r\n" : "\n";
      default:
        throw new Exception(
          pht("Unknown patch type '%s'!", $patch_type));
    }
  }

  public static function newFromArcBundle($path) {
    $path = Filesystem::resolvePath($path);

    $future = new ExecFuture(
      'tar tfO %s',
      $path);
    list($stdout, $file_list) = $future->resolvex();
    $file_list = explode("\n", trim($file_list));

    if (in_array('meta.json', $file_list)) {
      $future = new ExecFuture(
        'tar xfO %s meta.json',
        $path);
      $meta_info = $future->resolveJSON();
      $version       = idx($meta_info, 'version', 0);
      $base_revision = idx($meta_info, 'baseRevision');
      $revision_id   = idx($meta_info, 'revisionID');
      $encoding      = idx($meta_info, 'encoding');
      $author_name   = idx($meta_info, 'authorName');
      $author_email  = idx($meta_info, 'authorEmail');
    } else {
      // this arc bundle was probably made before we started storing meta info
      $version       = 0;
      $base_revision = null;
      $revision_id   = null;
      $encoding      = null;
      $author        = null;
    }

    $future = new ExecFuture(
      'tar xfO %s changes.json',
      $path);
    $changes = $future->resolveJSON();

    foreach ($changes as $change_key => $change) {
      foreach ($change['hunks'] as $key => $hunk) {
        list($hunk_data) = execx('tar xfO %s hunks/%s', $path, $hunk['corpus']);
        $changes[$change_key]['hunks'][$key]['corpus'] = $hunk_data;
      }
    }

    foreach ($changes as $change_key => $change) {
      $changes[$change_key] = ArcanistDiffChange::newFromDictionary($change);
    }

    $obj = new ArcanistBundle();
    $obj->changes = $changes;
    $obj->diskPath = $path;
    $obj->setBaseRevision($base_revision);
    $obj->setRevisionID($revision_id);
    $obj->setEncoding($encoding);

    return $obj;
  }

  public static function newFromDiff($data) {
    $obj = new ArcanistBundle();

    $parser = new ArcanistDiffParser();
    $obj->changes = $parser->parseDiff($data);

    return $obj;
  }

  private function __construct() {}

  public function writeToDisk($path) {
    $changes = $this->getChanges();

    $change_list = array();
    foreach ($changes as $change) {
      $change_list[] = $change->toDictionary();
    }

    $hunks = array();
    foreach ($change_list as $change_key => $change) {
      foreach ($change['hunks'] as $key => $hunk) {
        $hunks[] = $hunk['corpus'];
        $change_list[$change_key]['hunks'][$key]['corpus'] = count($hunks) - 1;
      }
    }

    $blobs = array();
    foreach ($change_list as $change) {
      if (!empty($change['metadata']['old:binary-phid'])) {
        $blobs[$change['metadata']['old:binary-phid']] = null;
      }
      if (!empty($change['metadata']['new:binary-phid'])) {
        $blobs[$change['metadata']['new:binary-phid']] = null;
      }
    }
    foreach ($blobs as $phid => $null) {
      $blobs[$phid] = $this->getBlob($phid);
    }

    $meta_info = array(
      'version'      => 5,
      'baseRevision' => $this->getBaseRevision(),
      'revisionID'   => $this->getRevisionID(),
      'encoding'     => $this->getEncoding(),
      'authorName'   => $this->getAuthorName(),
      'authorEmail'  => $this->getAuthorEmail(),
    );

    $dir = Filesystem::createTemporaryDirectory();
    Filesystem::createDirectory($dir.'/hunks');
    Filesystem::createDirectory($dir.'/blobs');
    Filesystem::writeFile($dir.'/changes.json', json_encode($change_list));
    Filesystem::writeFile($dir.'/meta.json', json_encode($meta_info));
    foreach ($hunks as $key => $hunk) {
      Filesystem::writeFile($dir.'/hunks/'.$key, $hunk);
    }
    foreach ($blobs as $key => $blob) {
      Filesystem::writeFile($dir.'/blobs/'.$key, $blob);
    }
    execx(
      '(cd %s; tar -czf %s *)',
      $dir,
      Filesystem::resolvePath($path));
    Filesystem::remove($dir);
  }

  public function toUnifiedDiff() {
    $this->reservedBytes = 0;

    $eol = $this->getEOL('unified');

    $result = array();
    $changes = $this->getChanges();
    foreach ($changes as $change) {
      $hunk_changes = $this->buildHunkChanges($change->getHunks(), $eol);
      if (!$hunk_changes) {
        continue;
      }

      $old_path = $this->getOldPath($change);
      $cur_path = $this->getCurrentPath($change);

      $index_path = $cur_path;
      if ($index_path === null) {
        $index_path = $old_path;
      }

      $result[] = 'Index: '.$index_path;
      $result[] = $eol;
      $result[] = str_repeat('=', 67);
      $result[] = $eol;

      if ($old_path === null) {
        $old_path = '/dev/null';
      }

      if ($cur_path === null) {
        $cur_path = '/dev/null';
      }

      // When the diff is used by `patch`, `patch` ignores what is listed as the
      // current path and just makes changes to the file at the old path (unless
      // the current path is '/dev/null'.
      // If the old path and the current path aren't the same (and neither is
      // /dev/null), this indicates the file was moved or copied. By listing
      // both paths as the new file, `patch` will apply the diff to the new
      // file.
      if ($cur_path !== '/dev/null' && $old_path !== '/dev/null') {
        $old_path = $cur_path;
      }

      $result[] = '--- '.$old_path.$eol;
      $result[] = '+++ '.$cur_path.$eol;

      $result[] = $hunk_changes;
    }

    if (!$result) {
      return '';
    }

    $diff = implode('', $result);
    return $this->convertNonUTF8Diff($diff);
  }

  public function toGitPatch() {
    $this->reservedBytes = 0;

    $eol = $this->getEOL('git');

    $result = array();
    $changes = $this->getChanges();

    $binary_sources = array();
    foreach ($changes as $change) {
      if (!$this->isGitBinaryChange($change)) {
        continue;
      }

      $type = $change->getType();
      if ($type == ArcanistDiffChangeType::TYPE_MOVE_AWAY ||
          $type == ArcanistDiffChangeType::TYPE_COPY_AWAY ||
          $type == ArcanistDiffChangeType::TYPE_MULTICOPY) {
        foreach ($change->getAwayPaths() as $path) {
          $binary_sources[$path] = $change;
        }
      }
    }

    foreach (array_keys($changes) as $multicopy_key) {
      $multicopy_change = $changes[$multicopy_key];

      $type = $multicopy_change->getType();
      if ($type != ArcanistDiffChangeType::TYPE_MULTICOPY) {
        continue;
      }

      // Decompose MULTICOPY into one MOVE_HERE and several COPY_HERE because
      // we need more information than we have in order to build a delete patch
      // and represent it as a bunch of COPY_HERE plus a delete. For details,
      // see T419.

      // Basically, MULTICOPY means there are 2 or more corresponding COPY_HERE
      // changes, so find one of them arbitrarily and turn it into a MOVE_HERE.

      // TODO: We might be able to do this more cleanly after T230 is resolved.

      $decompose_okay = false;
      foreach ($changes as $change_key => $change) {
        if ($change->getType() != ArcanistDiffChangeType::TYPE_COPY_HERE) {
          continue;
        }
        if ($change->getOldPath() != $multicopy_change->getCurrentPath()) {
          continue;
        }
        $decompose_okay = true;
        $change = clone $change;
        $change->setType(ArcanistDiffChangeType::TYPE_MOVE_HERE);
        $changes[$change_key] = $change;

        // The multicopy is now fully represented by MOVE_HERE plus one or more
        // COPY_HERE, so throw it away.
        unset($changes[$multicopy_key]);
        break;
      }

      if (!$decompose_okay) {
        throw new Exception(
          pht(
            'Failed to decompose multicopy changeset in '.
            'order to generate diff.'));
      }
    }

    foreach ($changes as $change) {
      $type = $change->getType();
      $file_type = $change->getFileType();

      if ($file_type == ArcanistDiffChangeType::FILE_DIRECTORY) {
        // TODO: We should raise a FYI about this, so the user is aware
        // that we omitted it, if the directory is empty or has permissions
        // which git can't represent.

        // Git doesn't support empty directories, so we simply ignore them. If
        // the directory is nonempty, 'git apply' will create it when processing
        // the changesets for files inside it.
        continue;
      }

      if ($type == ArcanistDiffChangeType::TYPE_MOVE_AWAY) {
        // Git will apply this in the corresponding MOVE_HERE.
        continue;
      }

      $old_mode = idx($change->getOldProperties(), 'unix:filemode', '100644');
      $new_mode = idx($change->getNewProperties(), 'unix:filemode', '100644');

      $is_binary = $this->isGitBinaryChange($change);

      if ($is_binary) {
        $old_binary = idx($binary_sources, $this->getCurrentPath($change));
        $change_body = $this->buildBinaryChange($change, $old_binary);
      } else {
        $change_body = $this->buildHunkChanges($change->getHunks(), $eol);
      }
      if ($type == ArcanistDiffChangeType::TYPE_COPY_AWAY) {
        // TODO: This is only relevant when patching old Differential diffs
        // which were created prior to arc pruning TYPE_COPY_AWAY for files
        // with no modifications.
        if (!strlen($change_body) && ($old_mode == $new_mode)) {
          continue;
        }
      }

      $old_path = $this->getOldPath($change);
      $cur_path = $this->getCurrentPath($change);

      if ($old_path === null) {
        $old_index = 'a/'.$cur_path;
        $old_target  = '/dev/null';
      } else {
        $old_index = 'a/'.$old_path;
        $old_target  = 'a/'.$old_path;
      }

      if ($cur_path === null) {
        $cur_index = 'b/'.$old_path;
        $cur_target  = '/dev/null';
      } else {
        $cur_index = 'b/'.$cur_path;
        $cur_target  = 'b/'.$cur_path;
      }

      $old_target = $this->encodeGitTargetPath($old_target);
      $cur_target = $this->encodeGitTargetPath($cur_target);

      $result[] = "diff --git {$old_index} {$cur_index}".$eol;

      if ($type == ArcanistDiffChangeType::TYPE_ADD) {
        $result[] = "new file mode {$new_mode}".$eol;
      }

      if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE ||
          $type == ArcanistDiffChangeType::TYPE_MOVE_HERE ||
          $type == ArcanistDiffChangeType::TYPE_COPY_AWAY ||
          $type == ArcanistDiffChangeType::TYPE_CHANGE) {
        if ($old_mode !== $new_mode) {
          $result[] = "old mode {$old_mode}".$eol;
          $result[] = "new mode {$new_mode}".$eol;
        }
      }

      if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE) {
        $result[] = "copy from {$old_path}".$eol;
        $result[] = "copy to {$cur_path}".$eol;
      } else if ($type == ArcanistDiffChangeType::TYPE_MOVE_HERE) {
        $result[] = "rename from {$old_path}".$eol;
        $result[] = "rename to {$cur_path}".$eol;
      } else if ($type == ArcanistDiffChangeType::TYPE_DELETE ||
                 $type == ArcanistDiffChangeType::TYPE_MULTICOPY) {
        $old_mode = idx($change->getOldProperties(), 'unix:filemode');
        if ($old_mode) {
          $result[] = "deleted file mode {$old_mode}".$eol;
        }
      }

      if ($change_body) {
        if (!$is_binary) {
          $result[] = "--- {$old_target}".$eol;
          $result[] = "+++ {$cur_target}".$eol;
        }
        $result[] = $change_body;
      }
    }

    $diff = implode('', $result).$eol;
    return $this->convertNonUTF8Diff($diff);
  }

  private function isGitBinaryChange(ArcanistDiffChange $change) {
    $file_type = $change->getFileType();
    return ($file_type == ArcanistDiffChangeType::FILE_BINARY ||
            $file_type == ArcanistDiffChangeType::FILE_IMAGE);
  }

  private function convertNonUTF8Diff($diff) {
    if ($this->encoding) {
      $diff = phutil_utf8_convert($diff, $this->encoding, 'UTF-8');
    }
    return $diff;
  }

  public function getChanges() {
    return $this->changes;
  }

  private function breakHunkIntoSmallHunks(ArcanistDiffHunk $base_hunk) {
    $context = 3;

    $results = array();
    $lines = phutil_split_lines($base_hunk->getCorpus());
    $n = count($lines);

    $old_offset = $base_hunk->getOldOffset();
    $new_offset = $base_hunk->getNewOffset();

    $ii = 0;
    $jj = 0;
    while ($ii < $n) {
      // Skip lines until we find the next line with changes. Note: this skips
      // both ' ' (no changes) and '\' (no newline at end of file) lines. If we
      // don't skip the latter, we may incorrectly generate a terminal hunk
      // that has no actual change information when a file doesn't have a
      // terminal newline and not changed near the end of the file. 'patch' will
      // fail to apply the diff if we generate a hunk that does not actually
      // contain changes.
      for ($jj = $ii; $jj < $n; ++$jj) {
        $char = $lines[$jj][0];
        if ($char == '-' || $char == '+') {
          break;
        }
      }
      if ($jj >= $n) {
        break;
      }

      $hunk_start = max($jj - $context, 0);


      // NOTE: There are two tricky considerations here.
      // We can not generate a patch with overlapping hunks, or 'git apply'
      // rejects it after 1.7.3.4.
      // We can not generate a patch with too much trailing context, or
      // 'patch' rejects it.
      // So we need to ensure that we generate disjoint hunks, but don't
      // generate any hunks with too much context.

      $old_lines = 0;
      $new_lines = 0;
      $hunk_adjust = 0;
      $last_change = $jj;
      $break_here = null;
      for (; $jj < $n; ++$jj) {
        if ($lines[$jj][0] == ' ') {

          if ($jj - $last_change > $context) {
            if ($break_here === null) {
              // We haven't seen a change in $context lines, so this is a
              // potential place to break the hunk. However, we need to keep
              // looking in case there is another change fewer than $context
              // lines away, in which case we have to merge the hunks.
              $break_here = $jj;
            }
          }

          // If the context value is "3" and there are 7 unchanged lines
          // between the two changes, we could either generate one or two hunks
          // and end up with the same number of output lines. If we generate
          // one hunk, the middle line will be a line of source. If we generate
          // two hunks, the middle line will be an "@@ -1,2 +3,4 @@" header.

          // We choose to generate two hunks because this is the behavior of
          // "diff -u". See PHI838.

          if ($jj - $last_change >= ($context * 2 + 1)) {
            // We definitely aren't going to merge this with the next hunk, so
            // break out of the loop. We'll end the hunk at $break_here.
            break;
          }
        } else {
          $break_here = null;
          $last_change = $jj;

          if ($lines[$jj][0] == '\\') {
            // When we have a "\ No newline at end of file" line, it does not
            // contribute to either hunk length.
            ++$hunk_adjust;
          } else if ($lines[$jj][0] == '-') {
            ++$old_lines;
          } else if ($lines[$jj][0] == '+') {
            ++$new_lines;
          }
        }
      }

      if ($break_here !== null) {
        $jj = $break_here;
      }

      $hunk_length = min($jj, $n) - $hunk_start;
      $count_length = ($hunk_length - $hunk_adjust);

      $hunk = new ArcanistDiffHunk();
      $hunk->setOldOffset($old_offset + $hunk_start - $ii);
      $hunk->setNewOffset($new_offset + $hunk_start - $ii);
      $hunk->setOldLength($count_length - $new_lines);
      $hunk->setNewLength($count_length - $old_lines);

      $corpus = array_slice($lines, $hunk_start, $hunk_length);
      $corpus = implode('', $corpus);
      $hunk->setCorpus($corpus);

      $results[] = $hunk;

      $old_offset += ($jj - $ii) - $new_lines;
      $new_offset += ($jj - $ii) - $old_lines;
      $ii = $jj;
    }

    return $results;
  }

  private function encodeGitTargetPath($path) {
    // See T8768. If a target path contains spaces, it must be terminated with
    // a tab. If we don't do this, Mercurial has the wrong behavior when
    // applying the patch. This results in a semantic trailing whitespace
    // character:
    //
    //   +++ b/X Y.txt\t
    //
    // Everyone is at fault here and there are no winners.

    if (strpos($path, ' ') !== false) {
      $path = $path."\t";
    }

    return $path;
  }


  private function getOldPath(ArcanistDiffChange $change) {
    $old_path = $change->getOldPath();
    $type = $change->getType();

    if (!strlen($old_path) ||
        $type == ArcanistDiffChangeType::TYPE_ADD) {
      $old_path = null;
    }

    return $old_path;
  }

  private function getCurrentPath(ArcanistDiffChange $change) {
    $cur_path = $change->getCurrentPath();
    $type = $change->getType();

    if (!strlen($cur_path) ||
        $type == ArcanistDiffChangeType::TYPE_DELETE ||
        $type == ArcanistDiffChangeType::TYPE_MULTICOPY) {
      $cur_path = null;
    }

    return $cur_path;
  }

  private function buildHunkChanges(array $hunks, $eol) {
    assert_instances_of($hunks, 'ArcanistDiffHunk');
    $result = array();
    foreach ($hunks as $hunk) {
      $small_hunks = $this->breakHunkIntoSmallHunks($hunk);
      foreach ($small_hunks as $small_hunk) {
        $o_off = $small_hunk->getOldOffset();
        $o_len = $small_hunk->getOldLength();
        $n_off = $small_hunk->getNewOffset();
        $n_len = $small_hunk->getNewLength();
        $corpus = $small_hunk->getCorpus();

        $this->reserveBytes(strlen($corpus));

        // NOTE: If the length is 1 it can be omitted. Since git does this,
        // we also do it so that "arc export --git" diffs are as similar to
        // real git diffs as possible, which helps debug issues.

        if ($o_len == 1) {
          $o_head = "{$o_off}";
        } else {
          $o_head = "{$o_off},{$o_len}";
        }

        if ($n_len == 1) {
          $n_head = "{$n_off}";
        } else {
          $n_head = "{$n_off},{$n_len}";
        }

        $result[] = "@@ -{$o_head} +{$n_head} @@".$eol;
        $result[] = $corpus;

        $last = substr($corpus, -1);
        if ($last !== false && $last != "\r" && $last != "\n") {
          $result[] = $eol;
        }
      }
    }
    return implode('', $result);
  }

  public function setLoadFileDataCallback($callback) {
    $this->loadFileDataCallback = $callback;
    return $this;
  }

  private function getBlob($phid, $name = null) {
    if ($this->loadFileDataCallback) {
      return call_user_func($this->loadFileDataCallback, $phid);
    }

    if ($this->diskPath) {
      list($blob_data) = execx('tar xfO %s blobs/%s', $this->diskPath, $phid);
      return $blob_data;
    }

    $console = PhutilConsole::getConsole();

    if ($this->conduit) {
      if ($name) {
        $console->writeErr(
          "%s\n",
          pht("Downloading binary data for '%s'...", $name));
      } else {
        $console->writeErr("%s\n", pht('Downloading binary data...'));
      }
      $data_base64 = $this->conduit->callMethodSynchronous(
        'file.download',
        array(
          'phid' => $phid,
        ));
      return base64_decode($data_base64);
    }

    throw new Exception(pht("Nowhere to load blob '%s' from!", $phid));
  }

  private function buildBinaryChange(ArcanistDiffChange $change, $old_binary) {
    $eol = $this->getEOL('git');

    // In Git, when we write out a binary file move or copy, we need the
    // original binary for the source and the current binary for the
    // destination.

    if ($old_binary) {
      if ($old_binary->getOriginalFileData() !== null) {
        $old_data = $old_binary->getOriginalFileData();
        $old_phid = null;
      } else {
        $old_data = null;
        $old_phid = $old_binary->getMetadata('old:binary-phid');
      }
    } else {
      $old_data = $change->getOriginalFileData();
      $old_phid = $change->getMetadata('old:binary-phid');
    }

    if ($old_data === null && $old_phid) {
      $name = basename($change->getOldPath());
      $old_data = $this->getBlob($old_phid, $name);
    }

    $old_length = strlen($old_data);

    // Here, and below, the binary will be emitted with base85 encoding. This
    // encoding encodes each 4 bytes of input in 5 bytes of output, so we may
    // need up to 5/4ths as many bytes to represent it.

    // We reserve space up front because base85 encoding isn't super cheap. If
    // the blob is enormous, we'd rather just bail out now before doing a ton
    // of work and then throwing it away anyway.

    // However, the data is compressed before it is emitted so we may actually
    // end up using fewer bytes. For now, the allocator just assumes the worst
    // case since it isn't important to be precise, but we could do a more
    // exact job of this.
    $this->reserveBytes($old_length * 5 / 4);

    if ($old_data === null) {
      $old_data = '';
      $old_sha1 = str_repeat('0', 40);
    } else {
      $old_sha1 = sha1("blob {$old_length}\0{$old_data}");
    }

    $new_phid = $change->getMetadata('new:binary-phid');

    $new_data = null;
    if ($change->getCurrentFileData() !== null) {
      $new_data = $change->getCurrentFileData();
    } else if ($new_phid) {
      $name = basename($change->getCurrentPath());
      $new_data = $this->getBlob($new_phid, $name);
    }

    $new_length = strlen($new_data);
    $this->reserveBytes($new_length * 5 / 4);

    if ($new_data === null) {
      $new_data = '';
      $new_sha1 = str_repeat('0', 40);
    } else {
      $new_sha1 = sha1("blob {$new_length}\0{$new_data}");
    }

    $content = array();
    $content[] = "index {$old_sha1}..{$new_sha1}".$eol;
    $content[] = 'GIT binary patch'.$eol;

    $content[] = "literal {$new_length}".$eol;
    $content[] = $this->emitBinaryDiffBody($new_data).$eol;

    $content[] = "literal {$old_length}".$eol;
    $content[] = $this->emitBinaryDiffBody($old_data).$eol;

    return implode('', $content);
  }

  private function emitBinaryDiffBody($data) {
    $eol = $this->getEOL('git');
    return self::newBase85Data($data, $eol);
  }

  public static function newBase85Data($data, $eol, $mode = null) {
    // The "32bit" and "64bit" modes are used by unit tests to verify that all
    // of the encoding pathways here work identically. In these modes, we skip
    // compression because `gzcompress()` may not be stable and we just want
    // to test that the output matches some expected result.

    if ($mode === null) {
      if (!function_exists('gzcompress')) {
        throw new Exception(
          pht(
            'This patch has binary data. The PHP zlib extension is required '.
            'to apply patches with binary data to git. Install the PHP zlib '.
            'extension to continue.'));
      }

      $input = gzcompress($data);
      $is_64bit = (PHP_INT_SIZE >= 8);
    } else {
      switch ($mode) {
        case '32bit':
          $input = $data;
          $is_64bit = false;
          break;
        case '64bit':
          $input = $data;
          $is_64bit = true;
          break;
        default:
          throw new Exception(
            pht(
              'Unsupported base85 encoding mode "%s".',
              $mode));
      }
    }

    // See emit_binary_diff_body() in diff.c for git's implementation.

    // This is implemented awkwardly in order to closely mirror git's
    // implementation in base85.c

    // It is also implemented awkwardly to work correctly on 32-bit machines.
    // Broadly, this algorithm converts the binary input to printable output
    // by transforming each 4 binary bytes of input to 5 printable bytes of
    // output, one piece at a time.
    //
    // To do this, we convert the 4 bytes into a 32-bit integer, then use
    // modulus and division by 85 to pick out printable bytes (85^5 is slightly
    // larger than 2^32). In C, this algorithm is fairly easy to implement
    // because the accumulator can be made unsigned.
    //
    // In PHP, there are no unsigned integers, so values larger than 2^31 break
    // on 32-bit systems under modulus:
    //
    //   $ php -r 'print (1 << 31) % 13;' # On a 32-bit machine.
    //   -11
    //
    // However, PHP's float type is an IEEE 754 64-bit double precision float,
    // so we can safely store integers up to around 2^53 without loss of
    // precision. To work around the lack of an unsigned type, we just use a
    // double and perform the modulus with fmod().
    //
    // (Since PHP overflows integer operations into floats, we don't need much
    // additional casting.)

    // On 64 bit systems, we skip all this fanfare and just use integers. This
    // is significantly faster.

    static $map = array(
      '0',
      '1',
      '2',
      '3',
      '4',
      '5',
      '6',
      '7',
      '8',
      '9',
      'A',
      'B',
      'C',
      'D',
      'E',
      'F',
      'G',
      'H',
      'I',
      'J',
      'K',
      'L',
      'M',
      'N',
      'O',
      'P',
      'Q',
      'R',
      'S',
      'T',
      'U',
      'V',
      'W',
      'X',
      'Y',
      'Z',
      'a',
      'b',
      'c',
      'd',
      'e',
      'f',
      'g',
      'h',
      'i',
      'j',
      'k',
      'l',
      'm',
      'n',
      'o',
      'p',
      'q',
      'r',
      's',
      't',
      'u',
      'v',
      'w',
      'x',
      'y',
      'z',
      '!',
      '#',
      '$',
      '%',
      '&',
      '(',
      ')',
      '*',
      '+',
      '-',
      ';',
      '<',
      '=',
      '>',
      '?',
      '@',
      '^',
      '_',
      '`',
      '{',
      '|',
      '}',
      '~',
    );

    $len_map = array();
    for ($ii = 0; $ii <= 52; $ii++) {
      if ($ii <= 26) {
        $len_map[$ii] = chr($ii + ord('A') - 1);
      } else {
        $len_map[$ii] = chr($ii - 26 + ord('a') - 1);
      }
    }

    $buf = '';

    $lines = str_split($input, 52);
    $final = (count($lines) - 1);

    foreach ($lines as $idx => $line) {
      if ($idx === $final) {
        $len = strlen($line);
      } else {
        $len = 52;
      }

      // The first character encodes the line length.
      $buf .= $len_map[$len];

      $pos = 0;
      while ($len) {
        $accum = 0;
        for ($count = 24; $count >= 0; $count -= 8) {
          $val = ord($line[$pos++]);
          $val = $val * (1 << $count);
          $accum = $accum + $val;
          if (--$len == 0) {
            break;
          }
        }

        $slice = '';

        // If we're in 64bit mode, we can just use integers. Otherwise, we
        // need to use floating point math to avoid overflows.

        if ($is_64bit) {
          for ($count = 4; $count >= 0; $count--) {
            $val = $accum % 85;
            $accum = $accum / 85;
            $slice .= $map[$val];
          }
        } else {
          for ($count = 4; $count >= 0; $count--) {
            $val = (int)fmod($accum, 85.0);
            $accum = floor($accum / 85.0);
            $slice .= $map[$val];
          }
        }

        $buf .= strrev($slice);
      }

      $buf .= $eol;
    }

    return $buf;
  }

  private function reserveBytes($bytes) {
    $this->reservedBytes += $bytes;

    if ($this->byteLimit) {
      if ($this->reservedBytes > $this->byteLimit) {
        throw new ArcanistDiffByteSizeException(
          pht(
            'This large diff requires more space than it is allowed to '.
            'use (limited to %s bytes; needs more than %s bytes).',
            new PhutilNumber($this->byteLimit),
            new PhutilNumber($this->reservedBytes)));
      }
    }

    return $this;
  }

}
