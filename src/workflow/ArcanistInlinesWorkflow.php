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
 * @group workflow
 */
final class ArcanistInlinesWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **inlines** [--revision __revision_id__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Display inline comments related to a particular revision.
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'revision' => array(
        'param' => 'revision_id',
        'help' =>
          "Display inline comments for a specific revision. If you do not ".
          "specify a revision, arc will look in the commit message at HEAD.",
      ),
      'root' => array(
        'param' => 'directory',
        'help' => "Specify a string printed in front of each path.",
      ),
    );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function run() {
    if ($this->getArgument('revision')) {
      $revision_id = $this->normalizeRevisionID($this->getArgument('revision'));
    } else {
      $revisions = $this->getRepositoryAPI()
        ->loadWorkingCopyDifferentialRevisions($this->getConduit(), array());
      $revision_id = head(ipull($revisions, 'id'));
    }

    if (!$revision_id) {
      throw new ArcanistUsageException("No revisions found.");
    }

    $comments = array_mergev(
      $this->getConduit()->callMethodSynchronous(
        'differential.getrevisioncomments',
        array(
          'ids' => array($revision_id),
          'inlines' => true,
        )));

    $authors = array();
    if ($comments) {
      $authors = $this->getConduit()->callMethodSynchronous(
        'user.query',
        array(
          'phids' => array_unique(ipull($comments, 'authorPHID')),
        ));
      $authors = ipull($authors, 'userName', 'phid');
    }

    $inlines = array();
    foreach ($comments as $comment) {
      $author = idx($authors, $comment['authorPHID']);
      foreach ($comment['inlines'] as $inline) {
        $file = $inline['filePath'];
        $line = $inline['lineNumber'];
        $inlines[$file][$line][] = "({$author}) {$inline['content']}";
      }
    }

    $root = $this->getArgument('root');
    ksort($inlines);
    foreach ($inlines as $file => $file_inlines) {
      ksort($file_inlines);
      foreach ($file_inlines as $line => $line_inlines) {
        foreach ($line_inlines as $content) {
          echo "{$root}{$file}:{$line}:{$content}\n";
        }
      }
    }
  }
}
