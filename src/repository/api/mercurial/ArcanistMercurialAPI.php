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

  public function getRelativeCommit() {
    // TODO: This is hardcoded.
    return 'tip~1';
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

    // TODO: This is critical and not yet implemented.

    return array();
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

}
