<?php

final class ArcanistMercurialLocalState
  extends ArcanistRepositoryLocalState {

  private $localCommit;
  private $localBranch;
  private $localBookmark;

  public function getLocalCommit() {
    return $this->localCommit;
  }

  public function getLocalBookmark() {
    return $this->localBookmark;
  }

  protected function executeSaveLocalState() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $markers = $api->newMarkerRefQuery()
      ->execute();

    $local_commit = null;
    foreach ($markers as $marker) {
      if ($marker->isCommitState()) {
        $local_commit = $marker->getCommitHash();
      }
    }

    if ($local_commit === null) {
      throw new Exception(
        pht(
          'Unable to identify the current commit in the working copy.'));
    }

    $this->localCommit = $local_commit;

    $local_branch = null;
    foreach ($markers as $marker) {
      if ($marker->isBranchState()) {
        $local_branch = $marker->getName();
        break;
      }
    }

    if ($local_branch === null) {
      throw new Exception(
        pht(
          'Unable to identify the current branch in the working copy.'));
    }

    if ($local_branch !== null) {
      $this->localBranch = $local_branch;
    }

    $local_bookmark = null;
    foreach ($markers as $marker) {
      if ($marker->isBookmark()) {
        if ($marker->getIsActive()) {
          $local_bookmark = $marker->getName();
          break;
        }
      }
    }

    if ($local_bookmark !== null) {
      $this->localBookmark = $local_bookmark;
    }

    $has_bookmark = ($this->localBookmark !== null);

    if ($has_bookmark) {
      $location = pht(
        'Saving local state (at "%s" on branch "%s", bookmarked as "%s").',
        $api->getDisplayHash($this->localCommit),
        $this->localBranch,
        $this->localBookmark);
    } else {
      $location = pht(
        'Saving local state (at "%s" on branch "%s").',
        $api->getDisplayHash($this->localCommit),
        $this->localBranch);
    }

    $log->writeTrace(pht('SAVE STATE'), $location);
  }

  protected function executeRestoreLocalState() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    if ($this->localBookmark !== null) {
      $location = pht(
        'Restoring local state (at "%s" on branch "%s", bookmarked as "%s").',
        $api->getDisplayHash($this->localCommit),
        $this->localBranch,
        $this->localBookmark);
    } else {
      $location = pht(
        'Restoring local state (at "%s" on branch "%s").',
        $api->getDisplayHash($this->localCommit),
        $this->localBranch);
    }

    $log->writeStatus(pht('LOAD STATE'), $location);

    $api->execxLocal('update -- %s', $this->localCommit);
    $api->execxLocal('branch --force -- %s', $this->localBranch);

    if ($this->localBookmark !== null) {
      $api->execxLocal('bookmark --force -- %s', $this->localBookmark);
    }
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
    $api = $this->getRepositoryAPI();
    $commands = array();

    $commands[] = csprintf(
      'hg update -- %s',
      $api->getDisplayHash($this->localCommit));

    $commands[] = csprintf(
      'hg branch --force -- %s',
      $this->localBranch);

    if ($this->localBookmark !== null) {
      $commands[] = csprintf(
        'hg bookmark --force -- %s',
        $this->localBookmark);
    }

    return $commands;
  }

  protected function saveStash() {
    $api = $this->getRepositoryAPI();
    $log = $this->getWorkflow()->getLogEngine();

    $stash_ref = sprintf(
      'arc-%s',
      Filesystem::readRandomCharacters(12));

    $api->execxLocalWithExtension(
      'shelve',
      'shelve --unknown --name %s --',
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

    $api->execxLocalWithExtension(
      'shelve',
      'unshelve --keep --name %s --',
      $stash_ref);
  }

  protected function discardStash($stash_ref) {
    $api = $this->getRepositoryAPI();

    $api->execxLocalWithExtension(
      'shelve',
      'shelve --delete %s --',
      $stash_ref);
  }

}
