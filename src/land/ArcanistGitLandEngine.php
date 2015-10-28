<?php

final class ArcanistGitLandEngine
  extends ArcanistLandEngine {

  private $localRef;
  private $localCommit;
  private $sourceCommit;
  private $mergedRef;
  private $restoreWhenDestroyed;

  public function execute() {
    $this->verifySourceAndTargetExist();
    $this->fetchTarget();

    $this->printLandingCommits();

    if ($this->getShouldPreview()) {
      $this->writeInfo(
        pht('PREVIEW'),
        pht('Completed preview of operation.'));
      return;
    }

    $this->saveLocalState();

    try {
      $this->identifyRevision();
      $this->updateWorkingCopy();

      if ($this->getShouldHold()) {
        $this->writeInfo(
          pht('HOLD'),
          pht('Holding change locally, it has not been pushed.'));
      } else {
        $this->pushChange();
        $this->reconcileLocalState();

        if ($this->getShouldKeep()) {
          echo tsprintf(
            "%s\n",
            pht('Keeping local branch.'));
        } else {
          $this->destroyLocalBranch();
        }

        $this->writeOkay(
          pht('DONE'),
          pht('Landed changes.'));
      }

      $this->restoreWhenDestroyed = false;
    } catch (Exception $ex) {
      $this->restoreLocalState();
      throw $ex;
    }
  }

  public function __destruct() {
    if ($this->restoreWhenDestroyed) {
      $this->writeWARN(
        pht('INTERRUPTED!'),
        pht('Restoring working copy to its original state.'));

      $this->restoreLocalState();
    }
  }

  protected function getLandingCommits() {
    $api = $this->getRepositoryAPI();

    list($out) = $api->execxLocal(
      'log --oneline %s..%s --',
      $this->getTargetFullRef(),
      $this->sourceCommit);

    $out = trim($out);

    if (!strlen($out)) {
      return array();
    } else {
      return phutil_split_lines($out, false);
    }
  }

  private function identifyRevision() {
    $api = $this->getRepositoryAPI();
    $api->execxLocal('checkout %s --', $this->getSourceRef());
    call_user_func($this->getBuildMessageCallback(), $this);
  }

  private function verifySourceAndTargetExist() {
    $api = $this->getRepositoryAPI();

    list($err) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getTargetFullRef());

    if ($err) {
      throw new Exception(
        pht(
          'Branch "%s" does not exist in remote "%s".',
          $this->getTargetOnto(),
          $this->getTargetRemote()));
    }

    list($err, $stdout) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getSourceRef());

    if ($err) {
      throw new Exception(
        pht(
          'Branch "%s" does not exist in the local working copy.',
          $this->getSourceRef()));
    }

    $this->sourceCommit = trim($stdout);
  }

  private function fetchTarget() {
    $api = $this->getRepositoryAPI();

    $ref = $this->getTargetFullRef();

    $this->writeInfo(
      pht('FETCH'),
      pht('Fetching %s...', $ref));

    $api->execxLocal(
      'fetch -- %s %s',
      $this->getTargetRemote(),
      $this->getTargetOnto());
  }

  private function updateWorkingCopy() {
    $api = $this->getRepositoryAPI();
    $source = $this->sourceCommit;

    $api->execxLocal(
      'checkout %s --',
      $this->getTargetFullRef());

    list($original_author, $original_date) = $this->getAuthorAndDate($source);

    try {
      if ($this->getShouldSquash()) {
        $api->execxLocal(
          'merge --no-stat --no-commit --squash -- %s',
          $source);
      } else {
        $api->execxLocal(
          'merge --no-stat --no-commit --no-ff -- %s',
          $source);
      }
    } catch (Exception $ex) {
      $api->execManualLocal('merge --abort');

      // TODO: Maybe throw a better or more helpful exception here?

      throw $ex;
    }

    $api->execxLocal(
      'commit --author %s --date %s -F %s --',
      $original_author,
      $original_date,
      $this->getCommitMessageFile());

    $this->getWorkflow()->didCommitMerge();

    list($stdout) = $api->execxLocal(
      'rev-parse --verify %s',
      'HEAD');
    $this->mergedRef = trim($stdout);
  }

  private function pushChange() {
    $api = $this->getRepositoryAPI();

    $this->writeInfo(
      pht('PUSHING'),
      pht('Pushing changes to "%s".', $this->getTargetFullRef()));

    list($err) = $api->execPassthru(
      'push -- %s %s:%s',
      $this->getTargetRemote(),
      $this->mergedRef,
      $this->getTargetOnto());

    if ($err) {
      throw new ArcanistUsageException(
        pht(
          'Push failed! Fix the error and run "%s" again.',
          'arc land'));
    }
  }

  private function reconcileLocalState() {
    $api = $this->getRepositoryAPI();

    // Try to put the user into the best final state we can. This is very
    // complicated because users are incredibly creative and their local
    // branches may have the same names as branches in the remote but no
    // relationship to them.

    if ($this->localRef != $this->getSourceRef()) {
      // The user ran `arc land X` but was on a different branch, so just put
      // them back wherever they were before.
      echo tsprintf(
        "%s\n",
        pht('Switching back to "%s".', $this->localRef));
      $this->restoreLocalState();
      return;
    }

    list($err) = $api->execManualLocal(
      'rev-parse --verify %s',
      $this->getTargetOnto());
    if ($err) {
      echo tsprintf(
        "%s\n",
        pht(
          'Local branch "%s" does not exist, staying on detached HEAD.',
          $this->getTargetOnto()));
      return;
    }

    list($err, $upstream) = $api->execManualLocal(
      'rev-parse --verify --symbolic-full-name %s',
      $this->getTargetOnto().'@{upstream}');
    if ($err) {
      echo tsprintf(
        "%s\n",
        pht(
          'Local branch "%s" has no upstream, staying on detached HEAD.',
          $this->getTargetOnto()));
      return;
    }

    $upstream = trim($upstream);
    $expect_upstream = 'refs/remotes/'.$this->getTargetFullRef();
    if ($upstream != $expect_upstream) {
      echo tsprintf(
        "%s\n",
        pht(
          'Local branch "%s" tracks remote "%s" (not target remote "%s"), '.
          'staying on detached HEAD.',
          $this->getTargetOnto(),
          $upstream,
          $expect_upstream));
      return;
    }

    list($stdout) = $api->execxLocal(
      'log %s..%s --',
      $this->mergedRef,
      $this->getTargetOnto());
    $stdout = trim($stdout);

    if (!strlen($stdout)) {
      echo tsprintf(
        "%s\n",
        pht(
          'Local "%s" tracks target remote "%s", checking out and '.
          'pulling changes.',
          $this->getTargetOnto(),
          $this->getTargetFullRef()));

      $api->execxLocal('checkout %s --', $this->getTargetOnto());
      $api->execxLocal('pull --');
      $api->execxLocal('submodule update --init --recursive');

      return;
    }

    if ($this->getTargetOnto() !== $this->getSourceRef()) {
      echo tsprintf(
        "%s\n",
        pht(
          'Local "%s" is ahead of remote "%s". Checking out but '.
          'not pulling changes.',
          $this->getTargetOnto(),
          $this->getTargetFullRef()));

      $api->execxLocal('checkout %s --', $this->getTargetOnto());
      $api->execxLocal('submodule update --init --recursive');

      return;
    }

    // In this case, the user did something like land a branch onto itself,
    // and the branch is tracking the correct remote. We're going to discard
    // the local state and reset it to the state we just pushed.

    echo tsprintf(
      "%s\n",
      pht(
        'Local "%s" landed into remote "%s", resetting local branch to '.
        'remote state.',
        $this->getTargetOnto(),
        $this->getTargetFullRef()));

    $api->execxLocal('checkout %s --', $this->getTargetOnto());
    $api->execxLocal('reset --hard %s --', $this->getTargetFullRef());
    $api->execxLocal('submodule update --init --recursive');
  }

  private function destroyLocalBranch() {
    $api = $this->getRepositoryAPI();

    if ($this->localRef == $this->getSourceRef()) {
      // If we landed a branch onto itself, don't destroy it.
      return;
    }

    $recovery_command = csprintf(
      'git checkout -b %R %R',
      $this->getSourceRef(),
      $this->sourceCommit);

    echo tsprintf(
      "%s\n",
      pht('Cleaning up branch "%s"...', $this->getSourceRef()));

    echo tsprintf(
      "%s\n",
      pht('(Use `%s` if you want it back.)', $recovery_command));

    $api->execxLocal('branch -D -- %s', $this->getSourceRef());
  }

  /**
   * Save the local working copy state so we can restore it later.
   */
  private function saveLocalState() {
    $api = $this->getRepositoryAPI();

    $this->localCommit = $api->getWorkingCopyRevision();

    list($ref) = $api->execxLocal('rev-parse --abbrev-ref HEAD');
    $ref = trim($ref);
    if ($ref === 'HEAD') {
      $ref = $this->localCommit;
    }

    $this->localRef = $ref;

    $this->restoreWhenDestroyed = true;
  }

  /**
   * Restore the working copy to the state it was in before we started
   * performing writes.
   */
  private function restoreLocalState() {
    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $this->localRef);
    $api->execxLocal('reset --hard %s --', $this->localCommit);
    $api->execxLocal('submodule update --init --recursive');

    $this->restoreWhenDestroyed = false;
  }

  private function getTargetFullRef() {
    return $this->getTargetRemote().'/'.$this->getTargetOnto();
  }

  private function getAuthorAndDate($commit) {
    $api = $this->getRepositoryAPI();

    // TODO: This is working around Windows escaping problems, see T8298.

    list($info) = $api->execxLocal(
      'log -n1 --format=%C %s --',
      '%aD%n%an%n%ae',
      $commit);

    $info = trim($info);
    list($date, $author, $email) = explode("\n", $info, 3);

    return array(
      "$author <{$email}>",
      $date,
    );
  }

}
