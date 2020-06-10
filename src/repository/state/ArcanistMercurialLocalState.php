<?php

final class ArcanistMercurialLocalState
  extends ArcanistRepositoryLocalState {

  private $localCommit;
  private $localBranch;

  protected function executeSaveLocalState() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    // TODO: Both of these can be pulled from "hg arc-ls-markers" more
    // efficiently.

    $this->localCommit = $api->getCanonicalRevisionName('.');

    list($branch) = $api->execxLocal('branch');
    $this->localBranch = trim($branch);

    $log->writeTrace(
      pht('SAVE STATE'),
      pht(
        'Saving local state (at "%s" on branch "%s").',
        $this->getDisplayHash($this->localCommit),
        $this->localBranch));
  }

  protected function executeRestoreLocalState() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $log->writeStatus(
      pht('LOAD STATE'),
      pht(
        'Restoring local state (at "%s" on branch "%s").',
        $this->getDisplayHash($this->localCommit),
        $this->localBranch));

    $api->execxLocal('update -- %s', $this->localCommit);
    $api->execxLocal('branch --force -- %s', $this->localBranch);
  }

  protected function executeDiscardLocalState() {
    return;
  }

  protected function canStashChanges() {
    $api = $this->getRepositoryAPI();
    return $api->getMercurialFeature('shelve');
  }

  protected function getIgnoreHints() {
    return array(
      pht(
        'To configure Mercurial to ignore certain files in the working '.
        'copy, add them to ".hgignore".'),
    );
  }

  protected function newRestoreCommandsForDisplay() {
    $commands = array();

    $commands[] = csprintf(
      'hg update -- %s',
      $this->getDisplayHash($this->localCommit));

    $commands[] = csprintf(
      'hg branch --force -- %s',
      $this->localBranch);

    return $commands;
  }

  protected function saveStash() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $stash_ref = sprintf(
      'arc-%s',
      Filesystem::readRandomCharacters(12));

    $api->execxLocal(
      '--config extensions.shelve= shelve --unknown --name %s --',
      $stash_ref);

    $log->writeStatus(
      pht('SHELVE'),
      pht('Shelving uncommitted changes from working copy.'));

    return $stash_ref;
  }

  protected function restoreStash($stash_ref) {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $log->writeStatus(
      pht('UNSHELVE'),
      pht('Restoring uncommitted changes to working copy.'));

    $api->execxLocal(
      '--config extensions.shelve= unshelve --keep --name %s --',
      $stash_ref);
  }

  protected function discardStash($stash_ref) {
    $api = $this->getRepositoryAPI();

    $api->execxLocal(
      '--config extensions.shelve= shelve --delete %s --',
      $stash_ref);
  }

}
