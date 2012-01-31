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
          Supports: git, svn
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

    $conduit = $this->getConduit();
    $repository_api = $this->getRepositoryAPI();

    $revision_future = $conduit->callMethod(
      'differential.find',
      array(
        'guids' => array($this->getUserPHID()),
        'query' => 'open',
      ));

    $revisions = array();
    foreach ($revision_future->resolve() as $revision_dict) {
      $revisions[] = ArcanistDifferentialRevisionRef::newFromDictionary(
        $revision_dict);
    }

    if (!$revisions) {
      echo "You have no open Differential revisions.\n";
      return 0;
    }


    foreach ($revisions as $revision) {
      $revision_path = Filesystem::resolvePath($revision->getSourcePath());
      $current_path  = Filesystem::resolvePath($repository_api->getPath());
      $from_here = ($revision_path == $current_path);

      printf(
        "  %15s | %s | D%d | %s\n",
        $revision->getStatusName(),
        $from_here ? '*' : ' ',
        $revision->getID(),
        $revision->getName());
    }

    return 0;
  }
}
