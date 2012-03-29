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
 * Converts changesets between different formats.
 *
 * @group diff
 */
final class ArcanistBundle {

  private $changes;
  private $conduit;
  private $blobs = array();
  private $diskPath;
  private $projectID;
  private $baseRevision;
  private $revisionID;
  private $encoding;

  public function setConduit(ConduitClient $conduit) {
    $this->conduit = $conduit;
  }

  public function setProjectID($project_id) {
    $this->projectID = $project_id;
  }

  public function getProjectID() {
    return $this->projectID;
  }

  public function setBaseRevision($base_revision) {
    $this->baseRevision = $base_revision;
  }

  public function setEncoding($encoding) {
    $this->encoding = $encoding;
    return $this;
  }

  public function getEncoding() {
    return $this->encoding;
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

  public static function newFromArcBundle($path) {
    $path = Filesystem::resolvePath($path);

    $future = new ExecFuture(
      csprintf(
        'tar tfO %s',
        $path));
    list($stdout, $file_list) = $future->resolvex();
    $file_list = explode("\n", trim($file_list));

    if (in_array('meta.json', $file_list)) {
      $future = new ExecFuture(
        csprintf(
          'tar xfO %s meta.json',
          $path));
      $meta_info = $future->resolveJSON();
      $version       = idx($meta_info, 'version', 0);
      $project_name  = idx($meta_info, 'projectName');
      $base_revision = idx($meta_info, 'baseRevision');
      $revision_id   = idx($meta_info, 'revisionID');
      $encoding      = idx($meta_info, 'encoding');
    // this arc bundle was probably made before we started storing meta info
    } else {
      $version       = 0;
      $project_name  = null;
      $base_revision = null;
      $revision_id   = null;
      $encoding      = null;
    }

    $future = new ExecFuture(
      csprintf(
        'tar xfO %s changes.json',
        $path));
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
    $obj->setProjectID($project_name);
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

  private function __construct() {

  }

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
      'version'      => 3,
      'projectName'  => $this->getProjectID(),
      'baseRevision' => $this->getBaseRevision(),
      'revisionID'   => $this->getRevisionID(),
      'encoding'     => $this->getEncoding(),
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

    $result = array();
    $changes = $this->getChanges();
    foreach ($changes as $change) {

      $old_path = $this->getOldPath($change);
      $cur_path = $this->getCurrentPath($change);

      $index_path = $cur_path;
      if ($index_path === null) {
        $index_path = $old_path;
      }

      $result[] = 'Index: '.$index_path;
      $result[] = str_repeat('=', 67);

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

      $result[] = '--- '.$old_path;
      $result[] = '+++ '.$cur_path;

      $result[] = $this->buildHunkChanges($change->getHunks());
    }

    $diff = implode("\n", $result)."\n";
    return $this->convertNonUTF8Diff($diff);
  }

  public function toGitPatch() {
    $result = array();
    $changes = $this->getChanges();

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
      // changes, so find one of them arbitrariy and turn it into a MOVE_HERE.

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
          "Failed to decompose multicopy changeset in order to generate diff.");
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

      $is_binary = ($file_type == ArcanistDiffChangeType::FILE_BINARY ||
                    $file_type == ArcanistDiffChangeType::FILE_IMAGE);

      if ($is_binary) {
        $change_body = $this->buildBinaryChange($change);
      } else {
        $change_body = $this->buildHunkChanges($change->getHunks());
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

      $result[] = "diff --git {$old_index} {$cur_index}";

      if ($type == ArcanistDiffChangeType::TYPE_ADD) {
        $result[] = "new file mode {$new_mode}";
      }

      if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE ||
          $type == ArcanistDiffChangeType::TYPE_MOVE_HERE ||
          $type == ArcanistDiffChangeType::TYPE_COPY_AWAY) {
        if ($old_mode !== $new_mode) {
          $result[] = "old mode {$old_mode}";
          $result[] = "new mode {$new_mode}";
        }
      }

      if ($type == ArcanistDiffChangeType::TYPE_COPY_HERE) {
        $result[] = "copy from {$old_path}";
        $result[] = "copy to {$cur_path}";
      } else if ($type == ArcanistDiffChangeType::TYPE_MOVE_HERE) {
        $result[] = "rename from {$old_path}";
        $result[] = "rename to {$cur_path}";
      } else if ($type == ArcanistDiffChangeType::TYPE_DELETE ||
                 $type == ArcanistDiffChangeType::TYPE_MULTICOPY) {
        $old_mode = idx($change->getOldProperties(), 'unix:filemode');
        if ($old_mode) {
          $result[] = "deleted file mode {$old_mode}";
        }
      }

      if (!$is_binary) {
        $result[] = "--- {$old_target}";
        $result[] = "+++ {$cur_target}";
      }
      $result[] = $change_body;
    }

    $diff = implode("\n", $result)."\n";
    return $this->convertNonUTF8Diff($diff);
  }

  private function convertNonUTF8Diff($diff) {
    $try_encoding_is_non_utf8 =
      ($this->encoding && strtoupper($this->encoding) != 'UTF-8');
    if ($try_encoding_is_non_utf8) {
      $diff = mb_convert_encoding($diff, $this->encoding, 'UTF-8');
      if (!$diff) {
        throw new Exception(
          "Attempted conversion of diff to encoding ".
          "'{$this->encoding}' failed. Have you specified ".
          "the proper encoding correctly?");
      }
    }
    return $diff;
  }

  public function getChanges() {
    return $this->changes;
  }

  private function breakHunkIntoSmallHunks(ArcanistDiffHunk $base_hunk) {
    $context = 3;

    $results = array();
    $lines = explode("\n", $base_hunk->getCorpus());
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

          if ($jj - $last_change > (($context + 1) * 2)) {
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
      $corpus = implode("\n", $corpus);
      $hunk->setCorpus($corpus);

      $results[] = $hunk;

      $old_offset += ($jj - $ii) - $new_lines;
      $new_offset += ($jj - $ii) - $old_lines;
      $ii = $jj;
    }

    return $results;
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

  private function buildHunkChanges(array $hunks) {
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

        $result[] = "@@ -{$o_head} +{$n_head} @@";
        $result[] = $corpus;
      }
    }
    return implode("\n", $result);
  }

  private function getBlob($phid) {
    if ($this->diskPath) {
      list($blob_data) = execx('tar xfO %s blobs/%s', $this->diskPath, $phid);
      return $blob_data;
    }

    if ($this->conduit) {
      echo "Downloading binary data...\n";
      $data_base64 = $this->conduit->callMethodSynchronous(
        'file.download',
        array(
          'phid' => $phid,
        ));
      return base64_decode($data_base64);
    }

    throw new Exception("Nowhere to load blob '{$phid} from!");
  }

  private function buildBinaryChange(ArcanistDiffChange $change) {
    $old_phid = $change->getMetadata('old:binary-phid', null);
    $new_phid = $change->getMetadata('new:binary-phid', null);

    $type = $change->getType();
    if ($type == ArcanistDiffChangeType::TYPE_ADD) {
      $old_null = true;
    } else {
      $old_null = false;
    }

    if ($type == ArcanistDiffChangeType::TYPE_DELETE) {
      $new_null = true;
    } else {
      $new_null = false;
    }

    if ($old_null) {
      $old_data = '';
      $old_length = 0;
      $old_sha1 = str_repeat('0', 40);
    } else {
      $old_data = $this->getBlob($old_phid);
      $old_length = strlen($old_data);
      $old_sha1 = sha1("blob {$old_length}\0{$old_data}");
    }

    if ($new_null) {
      $new_data = '';
      $new_length = 0;
      $new_sha1 = str_repeat('0', 40);
    } else {
      $new_data = $this->getBlob($new_phid);
      $new_length = strlen($new_data);
      $new_sha1 = sha1("blob {$new_length}\0{$new_data}");
    }

    $content = array();
    $content[] = "index {$old_sha1}..{$new_sha1}";
    $content[] = "GIT binary patch";

    $content[] = "literal {$new_length}";
    $content[] = $this->emitBinaryDiffBody($new_data);

    $content[] = "literal {$old_length}";
    $content[] = $this->emitBinaryDiffBody($old_data);

    return implode("\n", $content);
  }

  private function emitBinaryDiffBody($data) {
    // See emit_binary_diff_body() in diff.c for git's implementation.

    $buf = '';

    $deflated = gzcompress($data);
    $lines = str_split($deflated, 52);
    foreach ($lines as $line) {
      $len = strlen($line);
      // The first character encodes the line length.
      if ($len <= 26) {
        $buf .= chr($len + ord('A') - 1);
      } else {
        $buf .= chr($len - 26 + ord('a') - 1);
      }
      $buf .= $this->encodeBase85($line);
      $buf .= "\n";
    }

    $buf .= "\n";

    return $buf;
  }

  private function encodeBase85($data) {
    // This is implemented awkwardly in order to closely mirror git's
    // implementation in base85.c

    static $map = array(
      '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
      'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
      'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
      'U', 'V', 'W', 'X', 'Y', 'Z',
      'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
      'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
      'u', 'v', 'w', 'x', 'y', 'z',
      '!', '#', '$', '%', '&', '(', ')', '*', '+', '-',
      ';', '<', '=', '>', '?', '@', '^', '_', '`', '{',
      '|', '}', '~',
    );

    $buf = '';

    $pos = 0;
    $bytes = strlen($data);
    while ($bytes) {
      $accum = '0';
      for ($count = 24; $count >= 0; $count -= 8) {
        $val = ord($data[$pos++]);
        $val = bcmul($val, (string)(1 << $count));
        $accum = bcadd($accum, $val);
        if (--$bytes == 0) {
          break;
        }
      }
      $slice = '';
      for ($count = 4; $count >= 0; $count--) {
        $val = bcmod($accum, 85);
        $accum = bcdiv($accum, 85);
        $slice .= $map[$val];
      }
      $buf .= strrev($slice);
    }

    return $buf;
  }

}
