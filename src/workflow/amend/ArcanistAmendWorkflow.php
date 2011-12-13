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
 * Updates git commit messages after a revision is "Accepted".
 *
 * @group workflow
 */
class ArcanistAmendWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **amend** [--revision __revision_id__] [--show]
          Supports: git
          Amend the working copy after a revision has been accepted, so commits
          can be marked 'committed' and pushed upstream.
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
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException(
        "You may only run 'arc amend' in a git working copy.");
    }

    if (!$is_show) {
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

    if ($this->getArgument('revision')) {
      $revision_id = $this->normalizeRevisionID($this->getArgument('revision'));
    } else {
      $log = $repository_api->getGitCommitLog();
      $parser = new ArcanistDiffParser();
      $changes = $parser->parseDiff($log);
      if (count($changes) != 1) {
        throw new Exception("Expected one log.");
      }
      $change = reset($changes);
      if ($change->getType() != ArcanistDiffChangeType::TYPE_MESSAGE) {
        throw new Exception("Expected message change.");
      }
      $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $change->getMetadata('message'));
      $revision_id = $message->getRevisionID();
      if (!$revision_id) {
        throw new ArcanistUsageException(
          "No revision specified with '--revision', and no Differential ".
          "revision marker in HEAD.");
      }
    }

    // TODO: The old 'arc amend' had a check here to see if you were running
    // 'arc amend' with an explicit revision but HEAD already had another
    // revision in it. Maybe this is worth restoring?

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

    if ($is_show) {
      echo $message."\n";
    } else {
      $repository_api->amendGitHeadCommit($message);
      echo "Amended commit message.\n";

      $mark_workflow = $this->buildChildWorkflow(
        'mark-committed',
        array(
          '--finalize',
          $revision_id,
        ));
      $mark_workflow->run();
    }

    return 0;
  }

  protected function getSupportedRevisionControlSystems() {
    return array('git');
  }

}
