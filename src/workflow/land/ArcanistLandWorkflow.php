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
 * Lands a branch by rebasing, merging and amending it.
 *
 * @group workflow
 */
final class ArcanistLandWorkflow extends ArcanistBaseWorkflow {

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **land** [__options__] __branch__ [--onto __master__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git

          Land an accepted change (currently sitting in local feature branch
          __branch__) onto __master__ and push it to the remote. Then, delete
          the feature branch.

          In mutable repositories, this will perform a --squash merge (the
          entire branch will be represented by one commit on __master__). In
          immutable repositories (or when --merge is provided), it will perform
          a --no-ff merge (the branch will always be merged into __master__ with
          a merge commit).
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
      'onto' => array(
        'param' => 'master',
        'help' => "Land feature branch onto a branch other than ".
                  "'master' (default). You can change the default by setting ".
                  "'arc.land.onto.default' in your .arcconfig.",
      ),
      'hold' => array(
        'help' => "Prepare the change to be pushed, but do not actually ".
                  "push it.",
      ),
      'keep-branch' => array(
        'help' => "Keep the feature branch after pushing changes to the ".
                  "remote (by default, it is deleted).",
      ),
      'remote' => array(
        'param' => 'origin',
        'help' => "Push to a remote other than 'origin' (default).",
      ),
      'merge' => array(
        'help' => 'Perform a --no-ff merge, not a --squash merge. If the '.
                  'project is marked as having an immutable history, this is '.
                  'the default behavior.',
      ),
      'squash' => array(
        'help' => 'Perform a --squash merge, not a --no-ff merge. If the '.
                  'project is marked as having a mutable history, this is '.
                  'the default behavior.',
        'conflicts' => array(
          'merge' => '--merge and --squash are conflicting merge strategies.',
        ),
      ),
      'revision' => array(
        'param' => 'id',
        'help'  => 'Use the message from a specific revision, rather than '.
                   'inferring the revision based on branch content.',
      ),
      '*' => 'branch',
    );
  }

  public function run() {
    $branch = $this->getArgument('branch');
    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        "Specify exactly one branch to land changes from.");
    }
    $branch = head($branch);

    $onto_default = nonempty(
      $this->getWorkingCopy()->getConfig('arc.land.onto.default'),
      'master');

    $remote = $this->getArgument('remote', 'origin');
    $onto = $this->getArgument('onto', $onto_default);

    if ($this->getArgument('merge')) {
      $use_squash = false;
    } else if ($this->getArgument('squash')) {
      $use_squash = true;
    } else {
      $use_squash = !$this->isHistoryImmutable();
    }

    $repository_api = $this->getRepositoryAPI();
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException("'arc land' only supports git.");
    }

    list($err) = $repository_api->execManualLocal(
      'rev-parse --verify %s',
      $branch);

    if ($err) {
      throw new ArcanistUsageException("Branch '{$branch}' does not exist.");
    }

    $this->requireCleanWorkingCopy();
    $repository_api->parseRelativeLocalCommit(array($remote.'/'.$onto));

    $old_branch = $repository_api->getBranchName();

    $repository_api->execxLocal('checkout %s', $onto);

    echo phutil_console_format(
      "Switched to branch **%s**. Updating branch...\n",
      $onto);

    $repository_api->execxLocal('pull --ff-only');

    list($out) = $repository_api->execxLocal(
      'log %s/%s..%s',
      $remote,
      $onto,
      $onto);
    if (strlen(trim($out))) {
      throw new ArcanistUsageException(
        "Local branch '{$onto}' is ahead of '{$remote}/{$onto}', so landing ".
        "a feature branch would push additional changes. Push or reset the ".
        "changes in '{$onto}' before running 'arc land'.");
    }

    $repository_api->execxLocal(
      'checkout %s',
      $branch);

    echo phutil_console_format(
      "Switched to branch **%s**. Identifying and merging...\n",
      $branch);

    if ($use_squash) {
      chdir($repository_api->getPath());
      $err = phutil_passthru('git rebase %s', $onto);
      if ($err) {
        throw new ArcanistUsageException(
          "'git rebase {$onto}' failed. You can abort with 'git rebase ".
          "--abort', or resolve conflicts and use 'git rebase --continue' to ".
          "continue forward. After resolving the rebase, run 'arc land' ".
          "again.");
      }

      // Now that we've rebased, the merge-base of origin/master and HEAD may
      // be different. Reparse the relative commit.
      $repository_api->parseRelativeLocalCommit(array($remote.'/'.$onto));
    }

    $revision_id = $this->getArgument('revision');
    if ($revision_id) {
      $revision_id = $this->normalizeRevisionID($revision_id);
      $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if (!$revisions) {
        throw new ArcanistUsageException("No such revision 'D{$revision_id}'!");
      }
    } else {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array(
          'authors' => array($this->getUserPHID()),
        ));
    }

    if (!count($revisions)) {
      throw new ArcanistUsageException(
        "arc can not identify which revision exists on branch '{$branch}'. ".
        "Update the revision with recent changes to synchronize the branch ".
        "name and hashes, or use 'arc amend' to amend the commit message at ".
        "HEAD, or use '--revision <id>' to select a revision explicitly.");
    } else if (count($revisions) > 1) {
      $message =
        "There are multiple revisions on feature branch '{$branch}' which are ".
        "not present on '{$onto}':\n\n".
        $this->renderRevisionList($revisions)."\n".
        "Separate these revisions onto different branches, or use ".
        "'--revision <id>' to select one.";
      throw new ArcanistUsageException($message);
    }

    $revision = head($revisions);
    $rev_id = $revision['id'];
    $rev_title = $revision['title'];

    if ($revision['status'] != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $ok = phutil_console_confirm(
        "Revision 'D{$rev_id}: {$rev_title}' has not been accepted. Continue ".
        "anyway?");
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    echo "Landing revision 'D{$rev_id}: {$rev_title}'...\n";

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $revision['id'],
      ));

    $repository_api->execxLocal('checkout %s', $onto);

    if (!$use_squash) {
      // In immutable histories, do a --no-ff merge to force a merge commit with
      // the right message.
      chdir($repository_api->getPath());
      $err = phutil_passthru(
        'git merge --no-ff -m %s %s',
        $message,
        $branch);
      if ($err) {
        throw new ArcanistUsageException(
          "'git merge' failed. Your working copy has been left in a partially ".
          "merged state. You can: abort with 'git merge --abort'; or follow ".
          "the instructions to complete the merge.");
      }
    } else {
      // In mutable histories, do a --squash merge.
      $repository_api->execxLocal(
        'merge --squash --ff-only %s',
        $branch);
      $repository_api->execxLocal(
        'commit -m %s',
        $message);
    }

    if ($this->getArgument('hold')) {
      echo phutil_console_format(
        "Holding change in **%s**: it has NOT been pushed yet.\n",
        $onto);
    } else {
      echo "Pushing change...\n\n";

      chdir($repository_api->getPath());
      $err = phutil_passthru(
        'git push %s %s',
        $remote,
        $onto);

      if ($err) {
        throw new ArcanistUsageException("'git push' failed.");
      }

      $mark_workflow = $this->buildChildWorkflow(
        'mark-committed',
        array(
          '--finalize',
          '--quiet',
          $revision['id'],
        ));
      $mark_workflow->run();

      echo "\n";
    }

    if (!$this->getArgument('keep-branch')) {
      list($ref) = $repository_api->execxLocal(
        'rev-parse --verify %s',
        $branch);
      $ref = trim($ref);
      $recovery_command = csprintf(
        'git checkout -b %s %s',
        $branch,
        $ref);

      echo "Cleaning up feature branch...\n";
      echo "(Use `{$recovery_command}` if you want it back.)\n";
      $repository_api->execxLocal(
        'branch -D %s',
        $branch);
    }

    // If we were on some branch A and the user ran "arc land B", switch back
    // to A.
    if (($old_branch != $branch) && ($old_branch != $onto)) {
      $repository_api->execxLocal(
        'checkout %s',
        $old_branch);
      echo phutil_console_format(
        "Switched back to branch **%s**.\n",
        $old_branch);
    }

    echo "Done.\n";

    return 0;
  }

  protected function getSupportedRevisionControlSystems() {
    return array('git');
  }

}
