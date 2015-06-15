<?php

/**
 * Represents a change to an individual path.
 */
final class ArcanistDiffChange extends Phobject {

  protected $metadata = array();

  protected $oldPath;
  protected $currentPath;
  protected $awayPaths = array();

  protected $oldProperties = array();
  protected $newProperties = array();

  protected $commitHash;
  protected $type = ArcanistDiffChangeType::TYPE_CHANGE;
  protected $fileType = ArcanistDiffChangeType::FILE_TEXT;

  protected $hunks = array();

  private $needsSyntheticGitHunks;

  private $currentFileData;
  private $originalFileData;

  public function setOriginalFileData($original_file_data) {
    $this->originalFileData = $original_file_data;
    return $this;
  }

  public function getOriginalFileData() {
    return $this->originalFileData;
  }

  public function setCurrentFileData($current_file_data) {
    $this->currentFileData = $current_file_data;
    return $this;
  }

  public function getCurrentFileData() {
    return $this->currentFileData;
  }

  public function toDictionary() {
    $hunks = array();
    foreach ($this->hunks as $hunk) {
      $hunks[] = $hunk->toDictionary();
    }

    return array(
      'metadata'      => $this->metadata,
      'oldPath'       => $this->oldPath,
      'currentPath'   => $this->currentPath,
      'awayPaths'     => $this->awayPaths,
      'oldProperties' => $this->oldProperties,
      'newProperties' => $this->newProperties,
      'type'          => $this->type,
      'fileType'      => $this->fileType,
      'commitHash'    => $this->commitHash,
      'hunks'         => $hunks,
    );
  }

  public static function newFromDictionary(array $dict) {
    $hunks = array();
    foreach ($dict['hunks'] as $hunk) {
      $hunks[] = ArcanistDiffHunk::newFromDictionary($hunk);
    }

    $obj = new ArcanistDiffChange();
    $obj->metadata = $dict['metadata'];
    $obj->oldPath = $dict['oldPath'];
    $obj->currentPath = $dict['currentPath'];
    // TODO: The backend is shipping down some bogus data, e.g. diff 199453.
    // Should probably clean this up.
    $obj->awayPaths     = nonempty($dict['awayPaths'],     array());
    $obj->oldProperties = nonempty($dict['oldProperties'], array());
    $obj->newProperties = nonempty($dict['newProperties'], array());
    $obj->type = $dict['type'];
    $obj->fileType = $dict['fileType'];
    $obj->commitHash = $dict['commitHash'];
    $obj->hunks = $hunks;

    return $obj;
  }

  public static function newFromConduit(array $dicts) {
    $changes = array();
    foreach ($dicts as $dict) {
      $changes[] = self::newFromDictionary($dict);
    }
    return $changes;
  }

  public function getChangedLines($type) {
    $lines = array();
    foreach ($this->hunks as $hunk) {
      $lines += $hunk->getChangedLines($type);
    }
    return $lines;
  }

  public function getAllMetadata() {
    return $this->metadata;
  }

  public function setMetadata($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function getMetadata($key) {
    return idx($this->metadata, $key);
  }

  public function setCommitHash($hash) {
    $this->commitHash = $hash;
    return $this;
  }

  public function getCommitHash() {
    return $this->commitHash;
  }

  public function addAwayPath($path) {
    $this->awayPaths[] = $path;
    return $this;
  }

  public function getAwayPaths() {
    return $this->awayPaths;
  }

  public function setFileType($type) {
    $this->fileType = $type;
    return $this;
  }

  public function getFileType() {
    return $this->fileType;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setOldProperty($key, $value) {
    $this->oldProperties[$key] = $value;
    return $this;
  }

  public function setNewProperty($key, $value) {
    $this->newProperties[$key] = $value;
    return $this;
  }

  public function getOldProperties() {
    return $this->oldProperties;
  }

  public function getNewProperties() {
    return $this->newProperties;
  }

  public function setCurrentPath($path) {
    $this->currentPath = $this->filterPath($path);
    return $this;
  }

  public function getCurrentPath() {
    return $this->currentPath;
  }

  public function setOldPath($path) {
    $this->oldPath = $this->filterPath($path);
    return $this;
  }

  public function getOldPath() {
    return $this->oldPath;
  }

  public function addHunk(ArcanistDiffHunk $hunk) {
    $this->hunks[] = $hunk;
    return $this;
  }

  public function dropHunks() {
    $this->hunks = array();
    return $this;
  }

  public function getHunks() {
    return $this->hunks;
  }

  /**
   * @return array $old => array($new, )
   */
  public function buildLineMap() {
    $line_map = array();
    $old = 1;
    $new = 1;
    foreach ($this->getHunks() as $hunk) {
      for ($n = $old; $n < $hunk->getOldOffset(); $n++) {
        $line_map[$n] = array($n + $new - $old);
      }
      $old = $hunk->getOldOffset();
      $new = $hunk->getNewOffset();
      $olds = array();
      $news = array();
      $lines = explode("\n", $hunk->getCorpus());
      foreach ($lines as $line) {
        $type = substr($line, 0, 1);
        if ($type == '-' || $type == ' ') {
          $olds[] = $old;
          $old++;
        }
        if ($type == '+' || $type == ' ') {
          $news[] = $new;
          $new++;
        }
        if ($type == ' ' || $type == '') {
          $line_map += array_fill_keys($olds, $news);
          $olds = array();
          $news = array();
        }
      }
    }
    return $line_map;
  }

  public function convertToBinaryChange(ArcanistRepositoryAPI $api) {

    // Fill in the binary data from the working copy.

    $this->setOriginalFileData(
      $api->getOriginalFileData(
        $this->getOldPath()));

    $this->setCurrentFileData(
      $api->getCurrentFileData(
        $this->getCurrentPath()));

    $this->hunks = array();
    $this->setFileType(ArcanistDiffChangeType::FILE_BINARY);
    return $this;
  }

  protected function filterPath($path) {
    if ($path == '/dev/null') {
      return null;
    }
    return $path;
  }

  public function renderTextSummary() {

    $type = $this->getType();
    $file = $this->getFileType();

    $char = ArcanistDiffChangeType::getSummaryCharacterForChangeType($type);
    $attr = ArcanistDiffChangeType::getShortNameForFileType($file);
    if ($attr) {
      $attr = '('.$attr.')';
    }

    $summary = array();
    $summary[] = sprintf(
      '%s %5.5s %s',
      $char,
      $attr,
      $this->getCurrentPath());
    if (ArcanistDiffChangeType::isOldLocationChangeType($type)) {
      foreach ($this->getAwayPaths() as $path) {
        $summary[] = '             to: '.$path;
      }
    }
    if (ArcanistDiffChangeType::isNewLocationChangeType($type)) {
      $summary[] = '             from: '.$this->getOldPath();
    }

    return implode("\n", $summary);
  }

  public function getSymlinkTarget() {
    if ($this->getFileType() != ArcanistDiffChangeType::FILE_SYMLINK) {
      throw new Exception(pht('Not a symlink!'));
    }
    $hunks = $this->getHunks();
    $hunk = reset($hunks);
    $corpus = $hunk->getCorpus();
    $match = null;
    if (!preg_match('/^\+(?:link )?(.*)$/m', $corpus, $match)) {
      throw new Exception(pht('Failed to extract link target!'));
    }
    return trim($match[1]);
  }

  public function setNeedsSyntheticGitHunks($needs_synthetic_git_hunks) {
    $this->needsSyntheticGitHunks = $needs_synthetic_git_hunks;
    return $this;
  }

  public function getNeedsSyntheticGitHunks() {
    return $this->needsSyntheticGitHunks;
  }

}
