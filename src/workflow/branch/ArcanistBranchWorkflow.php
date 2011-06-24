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
 * Displays user's git branches
 *
 * @group workflow
 */
class ArcanistBranchWorkflow extends ArcanistBaseWorkflow {

  private $branches;

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **branch**
          Supports: git
          A wrapper on 'git branch'. It pulls data from Differential and
          displays the revision status next to the branch name.
          Branches are sorted in ascending order by the last commit time.
          By default branches with committed/abandoned revisions
          are not displayed.
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }


  public function getArguments() {
    return array(
      'view-all' => array(
        'help' =>
          "Include committed and abandoned revisions",
      ),
      'by-status' => array(
        'help' => 'Group output by revision status.',
      ),
    );
  }

  public function run() {
    $repository_api = $this->getRepositoryAPI();
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException(
        "arc branch is only supported under git."
      );
    }

    $this->branches = BranchInfo::loadAll($repository_api);
    $all_revisions = array_unique(
      array_filter(mpull($this->branches, 'getRevisionId')));
    $revision_status = $this->loadDifferentialStatuses($all_revisions);
    $owner = $repository_api->getRepositoryOwner();
    foreach ($this->branches as $branch) {
      if ($branch->getCommitAuthor() != $owner) {
        $branch->setStatus('Not Yours');
        continue;
      }

      $rev_id = $branch->getRevisionId();
      if ($rev_id) {
        $status = idx($revision_status, $rev_id, 'Unknown Status');
        $branch->setStatus($status);
      } else {
        $branch->setStatus('No Revision');
      }
    }
    if (!$this->getArgument('view-all')) {
      $this->filterOutFinished();
    }
    $this->printInColumns();
  }



  /**
   * Makes a conduit call to differential to find out revision statuses
   * based on their IDs
   */
  private function loadDifferentialStatuses($rev_ids) {
    $conduit = $this->getConduit();
    $revision_future = $conduit->callMethod(
      'differential.find',
      array(
        'guids' => $rev_ids,
        'query' => 'revision-ids',
      ));
    $revisions = array();
    foreach ($revision_future->resolve() as $revision_dict) {
      $revisions[] = ArcanistDifferentialRevisionRef::newFromDictionary(
        $revision_dict);
    }
    $statuses = mpull($revisions, 'getStatusName', 'getId');
    return $statuses;
  }

  /**
   * Removes the branches with status either committed or abandoned.
   */
  private function filterOutFinished() {
    foreach ($this->branches as $id => $branch) {
      if ($branch->isCurrentHead() ) {
        continue; //never filter the current branch
      }
      $status = $branch->getStatus();
      if ($status == 'Committed' || $status == 'Abandoned') {
        unset($this->branches[$id]);
      }
    }
  }

  public function printInColumns() {
    $longest_name = 0;
    $longest_status = 0;
    foreach ($this->branches as $branch) {
      $longest_name = max(strlen($branch->getFormattedName()), $longest_name);
      $longest_status = max(strlen($branch->getStatus()), $longest_status);
    }

    if ($this->getArgument('by-status')) {
      $by_status = mgroup($this->branches, 'getStatus');
      foreach (array('Accepted', 'Needs Revision',
                     'Needs Review', 'No Revision') as $status) {
        $branches = idx($by_status, $status);
        if (!$branches) {
          continue;
        }
        echo reset($branches)->getFormattedStatus()."\n";
        foreach ($branches as $branch) {
          $name_markdown = $branch->getFormattedName();
          $subject = $branch->getCommitSubject();
          $name_markdown = str_pad($name_markdown, $longest_name + 4, ' ');
          echo "  $name_markdown $subject\n";
        }
      }
    } else {
      foreach ($this->branches as $branch) {
        $name_markdown = $branch->getFormattedName();
        $status_markdown = $branch->getFormattedStatus();
        $subject = $branch->getCommitSubject();
        $subject_pad = $longest_status - strlen($branch->getStatus()) + 4;
        $name_markdown =
          str_pad($name_markdown, $longest_name + 4, ' ');
        $subject =
          str_pad($subject, strlen($subject) + $subject_pad, ' ', STR_PAD_LEFT);
        echo "$name_markdown $status_markdown $subject\n";
      }
    }
  }
}
