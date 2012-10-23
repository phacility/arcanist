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
 * Updates git commit messages after a revision is "Accepted".
 *
 * @group workflow
 */
final class ArcanistAmendWorkflow extends ArcanistBaseWorkflow {

  public function getWorkflowName() {
    return 'amend';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **amend** [--revision __revision_id__] [--show]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg
          Amend the working copy after a revision has been accepted, so commits
          can be marked 'committed' and pushed upstream.

          Supported in Mercurial 2.2 and newer.
EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return true;
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

  public function getArguments() {
    return array(
      'show' => array(
        'help' =>
          "Show the amended commit message, without modifying the working copy."
      ),
      'revision' => array(
        'param' => 'revision_id',
        'help' =>
          "Amend a specific revision. If you do not specify a revision, ".
          "arc will look in the commit message at HEAD.",
      ),
    );
  }

  public function run() {
    $is_show = $this->getArgument('show');

    $repository_api = $this->getRepositoryAPI();
    if (!$is_show) {
      if (!$repository_api->supportsAmend()) {
        throw new ArcanistUsageException(
          "You may only run 'arc amend' in a git or hg (version ".
          "2.2 or newer) working copy.");
      }

      if ($this->isHistoryImmutable()) {
        throw new ArcanistUsageException(
          "This project is marked as adhering to a conservative history ".
          "mutability doctrine (having an immutable local history), which ".
          "precludes amending commit messages. You can use 'arc merge' to ".
          "merge feature branches instead.");
      }
      if ($repository_api->getUncommittedChanges()) {
        throw new ArcanistUsageException(
          "You have uncommitted changes in this branch. Stage and commit (or ".
          "revert) them before proceeding.");
      }
    }

    $revision_id = null;
    if ($this->getArgument('revision')) {
      $revision_id = $this->normalizeRevisionID($this->getArgument('revision'));
    }

    if ($repository_api->supportsRelativeLocalCommits()) {
      $repository_api->setDefaultBaseCommit();
    }

    $in_working_copy = $repository_api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      array(
        'authors'   => array($this->getUserPHID()),
        'status'    => 'status-any',
      ));
    $in_working_copy = ipull($in_working_copy, null, 'id');

    if (!$revision_id) {
      if (count($in_working_copy) == 0) {
        throw new ArcanistUsageException(
          "No revision specified with '--revision', and no revisions found ".
          "in the working copy. Use '--revision <id>' to specify which ".
          "revision you want to amend.");
      } else if (count($in_working_copy) > 1) {
        $message = "More than one revision was found in the working copy:\n".
          $this->renderRevisionList($in_working_copy)."\n".
          "Use '--revision <id>' to specify which revision you want to ".
          "amend.";
        throw new ArcanistUsageException($message);
      } else {
        $revision_id = key($in_working_copy);
      }
    }

    $conduit = $this->getConduit();
    try {
      $message = $conduit->callMethodSynchronous(
        'differential.getcommitmessage',
        array(
          'revision_id' => $revision_id,
          'edit'        => false,
        )
      );
    } catch (ConduitClientException $ex) {
      if (strpos($ex->getMessage(), 'ERR_NOT_FOUND') === false) {
        throw $ex;
      } else {
        throw new ArcanistUsageException(
          "Revision D{$revision_id} does not exist."
        );
      }
    }

    $revision = $conduit->callMethodSynchronous(
      'differential.query',
      array(
        'ids' => array($revision_id),
      ));
    if (empty($revision)) {
      throw new Exception(
        "Failed to lookup information for 'D{$revision_id}'!");
    }
    $revision = head($revision);
    $revision_title = $revision['title'];

    if (!$is_show) {
      if ($revision_id && empty($in_working_copy[$revision_id])) {
        $ok = phutil_console_confirm(
          "The revision 'D{$revision_id}' does not appear to be in the ".
          "working copy. Are you sure you want to amend HEAD with the ".
          "commit message for 'D{$revision_id}: {$revision_title}'?");
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    if ($is_show) {
      echo $message."\n";
    } else {
      echo phutil_console_format(
        "Amending commit message to reflect revision **%s**.\n",
        "D{$revision_id}: {$revision_title}");

      $repository_api->amendCommit($message);

      $mark_workflow = $this->buildChildWorkflow(
        'close-revision',
        array(
          '--finalize',
          $revision_id,
        ));
      $mark_workflow->run();
    }

    return 0;
  }


  protected function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

}
