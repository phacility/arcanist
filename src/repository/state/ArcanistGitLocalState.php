<?php

final class ArcanistGitLocalState
  extends ArcanistRepositoryLocalState {

  private $localCommit;
  private $localRef;
  private $localPath;

  public function getLocalRef() {
    return $this->localRef;
  }

  public function getLocalPath() {
    return $this->localPath;
  }

  protected function executeSaveLocalState() {
    $api = $this->getRepositoryAPI();

    $commit = $api->getWorkingCopyRevision();

    list($ref) = $api->execxLocal('rev-parse --abbrev-ref HEAD');
    $ref = trim($ref);
    if ($ref === 'HEAD') {
      $ref = null;
      $where = pht(
        'Saving local state (at detached commit "%s").',
        $api->getDisplayHash($commit));
    } else {
      $where = pht(
        'Saving local state (on ref "%s" at commit "%s").',
        $ref,
        $api->getDisplayHash($commit));
    }

    $this->localRef = $ref;
    $this->localCommit = $commit;

    if ($ref !== null) {
      $this->localPath = $api->getPathToUpstream($ref);
    }

    $log = $this->getWorkflow()->getLogEngine();
    $log->writeTrace(pht('SAVE STATE'), $where);
  }

  protected function executeRestoreLocalState() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $ref = $this->localRef;
    $commit = $this->localCommit;

    if ($ref !== null) {
      $where = pht(
        'Restoring local state (to ref "%s" at commit "%s").',
        $ref,
        $api->getDisplayHash($commit));
    } else {
      $where = pht(
        'Restoring local state (to detached commit "%s").',
        $api->getDisplayHash($commit));
    }

    $log->writeStatus(pht('LOAD STATE'), $where);

    if ($ref !== null) {
      $api->execxLocal('checkout -B %s %s --', $ref, $commit);

      // TODO: We save, but do not restore, the upstream configuration of
      // this branch.

    } else {
      $api->execxLocal('checkout %s --', $commit);
    }

    $api->execxLocal('submodule update --init --recursive');
  }

  protected function executeDiscardLocalState() {
    // We don't have anything to clean up in Git.
    return;
  }

  protected function newRestoreCommandsForDisplay() {
    $api = $this->getRepositoryAPI();
    $ref = $this->localRef;
    $commit = $this->localCommit;

    $commands = array();

    if ($ref !== null) {
      $commands[] = csprintf(
        'git checkout -B %s %s --',
        $ref,
        $api->getDisplayHash($commit));
    } else {
      $commands[] = csprintf(
        'git checkout %s --',
        $api->getDisplayHash($commit));
    }

    // NOTE: We run "submodule update" in the real restore workflow, but
    // assume users can reasonably figure that out on their own.

    return $commands;
  }

  protected function canStashChanges() {
    return true;
  }

  protected function getIgnoreHints() {
    return array(
      pht(
        'To configure Git to ignore certain files in this working copy, '.
        'add the file paths to "%s".',
        '.git/info/exclude'),
    );
  }

  protected function saveStash() {
    $api = $this->getRepositoryAPI();

    // NOTE: We'd prefer to "git stash create" here, because using "push"
    // and "pop" means we're affecting the stash list as a side effect.

    // However, under Git 2.21.1, "git stash create" exits with no output,
    // no error, and no effect if the working copy contains only untracked
    // files. For now, accept mutations to the stash list.

    $api->execxLocal('stash push --include-untracked --');

    $log = $this->getWorkflow()->getLogEngine();
    $log->writeStatus(
      pht('SAVE STASH'),
      pht('Saved uncommitted changes from working copy.'));

    return true;
  }

  protected function restoreStash($stash_ref) {
    $api = $this->getRepositoryAPI();

    $log = $this->getWorkflow()->getLogEngine();
    $log->writeStatus(
      pht('LOAD STASH'),
      pht('Restoring uncommitted changes to working copy.'));

    // NOTE: Under Git 2.21.1, "git stash apply" does not accept "--".
    $api->execxLocal('stash apply');
  }

  protected function discardStash($stash_ref) {
    $api = $this->getRepositoryAPI();

    // NOTE: Under Git 2.21.1, "git stash drop" does not accept "--".
    $api->execxLocal('stash drop');
  }

  private function getDisplayStashRef($stash_ref) {
    return substr($stash_ref, 0, 12);
  }

}
