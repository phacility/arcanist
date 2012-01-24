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
 * Show which revision or revisions are in the working copy.
 *
 * @group workflow
 */
class ArcanistWhichWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **which** (svn)
      **which** [commit] (hg, git)
          Supports: svn, git, hg
          Shows which revision is in the working copy (or which revisions, if
          more than one matches).
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
      'any-author' => array(
        'help' => "Show revisions by any author, not just you.",
      ),
      'any-status' => array(
        'help' => "Show committed and abandoned revisions.",
      ),
      'id' => array(
        'help' => "If exactly one revision matches, print it to stdout. ".
                  "Otherwise, exit with an error. Intended for scripts.",
      ),
      '*' => 'commit',
    );
  }

  public function run() {

    $repository_api = $this->getRepositoryAPI();

    $commit = $this->getArgument('commit');
    if (count($commit)) {
      if (!$repository_api->supportsRelativeLocalCommits()) {
        throw new ArcanistUsageException(
          "This version control system does not support relative commits.");
      } else {
        $repository_api->parseRelativeLocalCommit($commit);
      }
    }

    $any_author = $this->getArgument('any-author');
    $any_status = $this->getArgument('any-status');

    $query = array(
      'authors' => $any_author
        ? null
        : array($this->getUserPHID()),
      'status' => $any_status
        ? 'status-any'
        : 'status-open',
    );

    $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      $query);

    if (empty($revisions)) {
      $this->writeStatusMessage("No matching revisions.\n");
      return 1;
    }

    if ($this->getArgument('id')) {
      if (count($revisions) == 1) {
        echo idx(head($revisions), 'id');
        return 0;
      } else {
        $this->writeStatusMessage("More than one matching revision.\n");
        return 1;
      }
    }

    foreach ($revisions as $revision) {
      echo 'D'.$revision['id'].' '.$revision['title']."\n";
    }

    return 0;
  }
}
