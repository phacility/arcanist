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
 * Represents a change to an individual path.
 *
 * @group diff
 */
class ArcanistDiffChange {

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

  public function getHunks() {
    return $this->hunks;
  }

  public function convertToBinaryChange() {
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
      "%s %5.5s %s",
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
      throw new Exception("Not a symlink!");
    }
    $hunks = $this->getHunks();
    $hunk = reset($hunks);
    $corpus = $hunk->getCorpus();
    $match = null;
    if (!preg_match('/^\+(?:link )?(.*)$/m', $corpus, $match)) {
      throw new Exception("Failed to extract link target!");
    }
    return trim($match[1]);
  }

}
