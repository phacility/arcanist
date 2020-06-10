<?php

final class ArcanistMercurialLocalState
  extends ArcanistRepositoryLocalState {

  private $localCommit;
  private $localRef;

  public function getLocalRef() {
    return $this->localRef;
  }

  public function getLocalPath() {
    return $this->localPath;
  }

  protected function executeSaveLocalState() {
    $api = $this->getRepositoryAPI();

    // TODO: We need to save the position of "." and the current active
    // branch, which may be any symbol at all. Both of these can be pulled
    // from "hg arc-ls-markers".

  }

  protected function executeRestoreLocalState() {
    $api = $this->getRepositoryAPI();

    // TODO: In Mercurial, we may want to discard commits we've created.
    // $repository_api->execxLocal(
    //   '--config extensions.mq= strip %s',
    //   $this->onto);

  }

  protected function executeDiscardLocalState() {
    // TODO: Fix this.
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
    // TODO: Provide this.
    return array();
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
