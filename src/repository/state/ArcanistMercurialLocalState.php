<?php

final class ArcanistMercurialLocalState
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
    // TODO: Fix this.
  }

  protected function executeRestoreLocalState() {
    $api = $this->getRepositoryAPI();
    // TODO: Fix this.

    // TODO: In Mercurial, we may want to discard commits we've created.
    // $repository_api->execxLocal(
    //   '--config extensions.mq= strip %s',
    //   $this->onto);

  }

  protected function executeDiscardLocalState() {
    // TODO: Fix this.
  }

  protected function canStashChanges() {
    // Depends on stash extension.
    return false;
  }

  protected function getIgnoreHints() {
    // TODO: Provide this.
    return array();
  }

  protected function newRestoreCommandsForDisplay() {
    // TODO: Provide this.
    return array();
  }

  protected function saveStash() {
    return null;
  }

  protected function restoreStash($stash_ref) {
    return null;
  }

  protected function discardStash($stash_ref) {
    return null;
  }

}
