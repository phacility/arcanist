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
 * Holds information about a single git branch, and provides methods
 * for loading and display.
 */
final class BranchInfo {

  private $branchName;
  private $currentHead = false;
  private $revisionID = null;
  private $sha1;
  private $status;
  private $commitAuthor;
  private $commitTime;
  private $commitSubject;

  /**
   * Retrives all the branches from the current git repository,
   * and parses their commit messages.
   *
   * @return array a list of BranchInfo objects, one per branch.
   */
  public static function loadAll(ArcanistGitAPI $api) {
    $branches_raw = $api->getAllBranches();
    $branches = array();
    foreach ($branches_raw as $branch_raw) {
      $branch_info = new BranchInfo($branch_raw['name']);
      $branch_info->setSha1($branch_raw['sha1']);
      if ($branch_raw['current']) {
        $branch_info->setCurrent();
      }
      $branches[] = $branch_info;
    }

    $name_sha1_map = mpull($branches, 'getSha1', 'getName');
    $commits_list = $api->multigetCommitMessages(
      array_unique(array_values($name_sha1_map)),
      "%%ct%%n%%an%%n%%s%%n%%b"); //don't ask
    foreach ($branches as $branch) {
      $sha1 = $name_sha1_map[$branch->getName()];
      $branch->setSha1($sha1);
      $branch->parseCommitMessage($commits_list[$sha1]);
    }
    $branches = msort($branches, 'getCommitTime');
    return $branches;
  }

  public function __construct($branch_name) {
    $this->branchName = $branch_name;
  }

  public function setSha1($sha1) {
    $this->sha1 = $sha1;
    return $this;
  }

  public function getSha1() {
    return $this->sha1;
  }

  public function setCurrent() {
    $this->currentHead = true;
  }

  public function isCurrentHead() {
    return $this->currentHead;
  }


  public function setStatus($status) {
    $this->status = $status;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getRevisionID() {
    return $this->revisionID;
  }

  public function getCommitTime() {
    return $this->commitTime;
  }

  public function getCommitSubject() {
    return $this->commitSubject;
  }

  public function getCommitDisplayName() {
    if ($this->revisionID) {
      return 'D'.$this->revisionID.': '.$this->commitSubject;
    } else {
      return $this->commitSubject;
    }
  }

  public function getCommitAuthor() {
    return $this->commitAuthor;
  }

  public function getName() {
    return $this->branchName;
  }

  /**
   * Based on the 'git show' output extracts the commit date, author,
   * subject nad Differential revision .
   * 'Differential Revision:'
   *
   * @param string message output of git show -s --format="format:%ct%n%cn%n%b"
   */
  public function parseCommitMessage($message) {
    $message_lines = explode("\n", trim($message));
    $this->commitTime = $message_lines[0];
    $this->commitAuthor = $message_lines[1];
    $this->commitSubject = trim($message_lines[2]);
    $this->revisionID =
      ArcanistDifferentialCommitMessage::newFromRawCorpus($message)
      ->getRevisionID();
  }

  public function getFormattedName() {
    $res = "";
    if ($this->currentHead) {
      $res = '* ';
    }
    $res .= $this->branchName;
    return phutil_console_format('**%s**', $res);

  }

  /**
   * Generates a colored status name
   */
  public function getFormattedStatus() {
    return phutil_console_format(
      '<fg:'.$this->getColorForStatus().'>%s</fg>',
      $this->status);
  }

  /**
   * Assigns a pretty color based on the status
   */
  private function getColorForStatus() {
    static $status_to_color = array(
      'Committed' => 'cyan',
      'Needs Review' => 'magenta',
      'Needs Revision' => 'red',
      'Accepted' => 'green',
      'No Revision' => 'blue',
      'Abandoned' => 'default',
    );
    return idx($status_to_color, $this->status, 'default');
  }

}
