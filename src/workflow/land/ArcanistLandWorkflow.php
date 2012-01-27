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
class ArcanistLandWorkflow extends ArcanistBaseWorkflow {

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
      **land** __branch__ [--onto __master__]
          Supports: git

          Land an accepted change (currently sitting in local feature branch
          __branch__) onto __master__ and push it to the remote. Then, delete
          the feature branch.

          In mutable repositories, this will perform a --squash merge (the
          entire branch will be represented by one commit on __master__). In
          immutable repositories, it will perform a --no-ff merge (the branch
          will always be merged into __master__ with a merge commit).

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
                  "'master' (default).",
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
      '*' => 'branch',
    );
  }

  public function run() {
    $this->writeStatusMessage(
      phutil_console_format(
        "**WARNING:** 'arc land' is new and experimental.\n"));

    $branch = $this->getArgument('branch');
    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        "Specify exactly one branch to land changes from.");
    }
    $branch = head($branch);

    $remote = $this->getArgument('remote', 'origin');
    $onto = $this->getArgument('onto', 'master');
    $is_immutable = $this->isHistoryImmutable();

    $repository_api = $this->getRepositoryAPI();
    if (!($repository_api instanceof ArcanistGitAPI)) {
      throw new ArcanistUsageException("'arc land' only supports git.");
    }

    list($err) = exec_manual(
      '(cd %s && git rev-parse --verify %s)',
      $repository_api->getPath(),
      $branch);

    if ($err) {
      throw new ArcanistUsageException("Branch '{$branch}' does not exist.");
    }

    $this->requireCleanWorkingCopy();
    $repository_api->parseRelativeLocalCommit(array($remote.'/'.$onto));

    execx(
      '(cd %s && git checkout %s)',
      $repository_api->getPath(),
      $onto);

    echo phutil_console_format(
      "Switched to branch **%s**. Updating branch...\n",
      $onto);

    execx(
      '(cd %s && git pull --ff-only)',
      $repository_api->getPath());

    list($out) = execx(
      '(cd %s && git log %s/%s..%s)',
      $repository_api->getPath(),
      $remote,
      $onto,
      $onto);
    if (strlen(trim($out))) {
      throw new ArcanistUsageException(
        "Local branch '{$onto}' is ahead of '{$remote}/{$onto}', so landing ".
        "a feature branch would push additional changes. Push or reset the ".
        "changes in '{$onto}' before running 'arc land'.");
    }

    execx(
      '(cd %s && git checkout %s)',
      $repository_api->getPath(),
      $branch);

    echo phutil_console_format(
      "Switched to branch **%s**. Identifying and merging...\n",
      $branch);

    if (!$is_immutable) {
      $err = phutil_passthru(
        '(cd %s && git rebase %s)',
        $repository_api->getPath(),
        $onto);
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

    $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
      $this->getConduit(),
      array(
        'authors' => array($this->getUserPHID()),
      ));

    if (!count($revisions)) {
      throw new ArcanistUsageException(
        "arc can not identify which revision exists on branch '{$branch}'. ".
        "Update the revision with recent changes to synchronize the branch ".
        "name and hashes, or use 'arc amend' to amend the commit message at ".
        "HEAD.");
    } else if (count($revisions) > 1) {
      $message =
        "There are multiple revisions on feature branch '{$branch}' which are ".
        "not present on '{$onto}':\n\n".
        $this->renderRevisionList($revisions)."\n".
        "Separate these revisions onto different branches, or manually land ".
        "them in '{$onto}'.";
      throw new ArcanistUsageException($message);
    }

    $revision = head($revisions);
    $rev_id = $revision['id'];
    $rev_title = $revision['title'];

    if ($revision['status'] != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $ok = phutil_console_confirm(
        "Revision 'D{$id}: {$rev_title}' has not been accepted. Continue ".
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

    execx(
      '(cd %s && git checkout %s)',
      $repository_api->getPath(),
      $onto);

    if ($is_immutable) {
      // In immutable histories, do a --no-ff merge to force a merge commit with
      // the right message.
      $err = phutil_passthru(
        '(cd %s && git merge --no-ff -m %s %s)',
        $repository_api->getPath(),
        $message,
        $branch);
      if ($err) {
        throw new ArcanistUsageException(
          "'git merge' failed. Your working copy has been left in a partially ".
          "merged state. You can: abort with 'git merge --abort'; or follow ".
          "the instructions to complete the merge, and then push.");
      }
    } else {
      // In mutable histories, do a --squash merge.
      execx(
        '(cd %s && git merge --squash --ff-only %s)',
        $repository_api->getPath(),
        $branch);
      execx(
        '(cd %s && git commit -m %s)',
        $repository_api->getPath(),
        $message);
    }

    if ($this->getArgument('hold')) {
      echo phutil_console_format(
        "Holding change in **%s**: it has NOT been pushed yet.\n",
        $onto);
    } else {
      echo "Pushing change...\n\n";

      $err = phutil_passthru(
        '(cd %s && git push %s %s)',
        $repository_api->getPath(),
        $remote,
        $onto);

      if ($err) {
        throw new ArcanistUsageException("'git push' failed.");
      }

      echo "\n";
    }

    if (!$this->getArgument('keep-branch')) {
      echo "Cleaning up feature branch...\n";
      execx(
        '(cd %s && git branch -D %s)',
        $repository_api->getPath(),
        $branch);
    }

    echo "Done.\n";

    return 0;
  }

  protected function getSupportedRevisionControlSystems() {
    return array('git');
  }

}
