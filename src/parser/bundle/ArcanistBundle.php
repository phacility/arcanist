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

class ArcanistBundle {

  private $changes;

  public static function newFromChanges(array $changes) {
    $obj = new ArcanistBundle();
    $obj->changes = $changes;
    return $obj;
  }

  public static function newFromArcBundle($path) {
    $path = Filesystem::resolvePath($path);

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

    $dir = Filesystem::createTemporaryDirectory();
    Filesystem::createDirectory($dir.'/hunks');
    Filesystem::createDirectory($dir.'/blobs');
    Filesystem::writeFile($dir.'/changes.json', json_encode($change_list));
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

      $result[] = '--- '.$old_path;
      $result[] = '+++ '.$cur_path;

      $result[] = $this->buildHunkChanges($change->getHunks());
    }

    return implode("\n", $result)."\n";
  }

  public function toGitPatch() {
    $result = array();
    $changes = $this->getChanges();
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

      $change_body = $this->buildHunkChanges($change->getHunks());
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

      $result[] = "--- {$old_target}";
      $result[] = "+++ {$cur_target}";
      $result[] = $change_body;
    }
    return implode("\n", $result)."\n";
  }

  public function getChanges() {
    return $this->changes;
  }

  private function breakHunkIntoSmallHunks(ArcanistDiffHunk $hunk) {
    $context = 3;

    $results = array();
    $lines = explode("\n", $hunk->getCorpus());
    $n = count($lines);

    $old_offset = $hunk->getOldOffset();
    $new_offset = $hunk->getNewOffset();

    $ii = 0;
    $jj = 0;
    while ($ii < $n) {
      for ($jj = $ii; $jj < $n && $lines[$jj][0] == ' '; ++$jj) {
        // Skip lines until we find the first line with changes.
      }
      if ($jj >= $n) {
        break;
      }

      $hunk_start = max($jj - $context, 0);

      $old_lines = 0;
      $new_lines = 0;
      $last_change = $jj;
      for (; $jj < $n; ++$jj) {
        if ($lines[$jj][0] == ' ') {
          if ($jj - $last_change > $context) {
            break;
          }
        } else {
          $last_change = $jj;
          if ($lines[$jj][0] == '-') {
            ++$old_lines;
          } else {
            ++$new_lines;
          }
        }
      }

      $hunk_length = min($jj, $n) - $hunk_start;

      $hunk = new ArcanistDiffHunk();
      $hunk->setOldOffset($old_offset + $hunk_start - $ii);
      $hunk->setNewOffset($new_offset + $hunk_start - $ii);
      $hunk->setOldLength($hunk_length - $new_lines);
      $hunk->setNewLength($hunk_length - $old_lines);

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
    $result = array();
    foreach ($hunks as $hunk) {
      $small_hunks = $this->breakHunkIntoSmallHunks($hunk);
      foreach ($small_hunks as $small_hunk) {
        $o_off = $small_hunk->getOldOffset();
        $o_len = $small_hunk->getOldLength();
        $n_off = $small_hunk->getNewOffset();
        $n_len = $small_hunk->getNewLength();
        $corpus = $small_hunk->getCorpus();

        $result[] = "@@ -{$o_off},{$o_len} +{$n_off},{$n_len} @@";
        $result[] = $corpus;
      }
    }
    return implode("\n", $result);
  }

}
