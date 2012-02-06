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
 * Lists open revisions in Differential.
 *
 * @group workflow
 */
final class ArcanistListWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **list**
          Supports: git, svn, hg
          List your open Differential revisions.
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

  public function run() {
    $revisions = $this->getConduit()->callMethodSynchronous(
      'differential.query',
      array(
        'authors' => array($this->getUserPHID()),
        'status'  => 'status-open',
      ));

    if (!$revisions) {
      echo "You have no open Differential revisions.\n";
      return 0;
    }

    $repository_api = $this->getRepositoryAPI();

    $info = array();

    $status_len = 0;
    foreach ($revisions as $key => $revision) {
      $revision_path = Filesystem::resolvePath($revision['sourcePath']);
      $current_path  = Filesystem::resolvePath($repository_api->getPath());
      if ($revision_path == $current_path) {
        $info[$key]['here'] = 1;
      } else {
        $info[$key]['here'] = 0;
      }
      $info[$key]['sort'] = sprintf(
        '%d%04d%08d',
        $info[$key]['here'],
        $revision['status'],
        $revision['id']);
      $info[$key]['statusColorized'] =
        BranchInfo::renderColorizedRevisionStatus(
          $revision['statusName']);
      $status_len = max(
        $status_len,
        strlen($info[$key]['statusColorized']));
    }

    $info = isort($info, 'sort');
    foreach ($info as $key => $spec) {
      $revision = $revisions[$key];
      printf(
        "%s %-".($status_len + 4)."s D%d: %s\n",
        $spec['here']
          ? phutil_console_format('**%s**', '*')
          : ' ',
        $spec['statusColorized'],
        $revision['id'],
        $revision['title']);
    }

    return 0;
  }
}
