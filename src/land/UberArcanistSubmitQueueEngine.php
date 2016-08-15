<?php

final class UberArcanistSubmitQueueEngine
    extends ArcanistGitLandEngine
{
  private $revision;
  private $shouldShadow;

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
      $this->updateWorkingCopy();
      $this->updateRevision();

      $this->pushChange();
      if ($this->shouldShadow) {
        // do nothing
      } else {
        // cleanup the local state
        $this->reconcileLocalState();

        if ($this->getShouldKeep()) {
          echo tsprintf(
            "%s\n",
            pht('Keeping local branch.'));
        } else {
          $this->checkoutTarget();
          $this->destroyLocalBranch();
        }
      }
      $this->restoreWhenDestroyed = false;
    }  catch (Exception $ex) {
      $this->restoreLocalState();
      throw $ex;
    }
  }

  private function checkoutTarget() {
    $api = $this->getRepositoryAPI();
    $api->execxLocal("checkout %s --", $this->getTargetOnto());
  }

  private function pushChange() {
    $this->writeInfo(
      pht('PUSHING'),
      pht('Pushing changes to Submit Queue.'));
    $api = $this->getRepositoryAPI();
    list($out) = $api->execxLocal(
      'config --get remote.%s.url',
      $this->getTargetRemote());

    $remoteUrl = trim($out);
    // Get the latest revision as we could have updated the diff
    // as a result of arc diff
    $revision = $this->getRevision();
    $statusUrl = $this->submitQueueClient->submitMergeRequest(
      $remoteUrl,
      $revision['diffs'][0],
      $revision['id'],
      $this->shouldShadow);
    $this->writeInfo(
      pht('Successfully submitted the request to the Submit Queue.'),
      pht('Please use "%s" to track your changes', $statusUrl));
     $this->writeInfo(
       pht('If the Submit Queue request fails,'),
       pht('please do arc restore "%s" to restore your branch', 'whatever'));
  }

  public function __construct($submitQueueClient, $conduit) {
    $this->submitQueueClient = $submitQueueClient;
    $this->conduit = $conduit;
  }

  private function updateWorkingCopy() {
    $api = $this->getRepositoryAPI();

    $api->execxLocal('checkout %s --', $this->getSourceRef());

    try {
      // merge target against source to generate the latest patch
      $api->execxLocal('merge --no-stat %s --', $this->getTargetFullRef());
    } catch (Exception $ex) {
      $api->execManualLocal('merge --abort');
      $api->execManualLocal('reset --hard HEAD --');

      throw new Exception(
        pht(
          '"%s" does not merge cleanly into Local "%s". Merge or rebase '.
          'local changes so they can merge cleanly.',
          $this->getTargetFullRef(),
          $this->getSourceRef()));
    }
  }

  final public function getRevision() {
    return $this->revision;
  }

  final public function setShouldShadow($shouldShadow) {
    $this->shouldShadow = $shouldShadow;
    return $this;
  }

  final public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  private function updateRevision() {
    $api = $this->getRepositoryAPI();
    $local_diff = $this->normalizeDiff(
      $api->getFullGitDiff(
        $api->getBaseCommit(),
        $api->getHeadCommit()));

    $reviewed_diff = $this->normalizeDiff(
      $this->conduit->callMethodSynchronous(
        'differential.getrawdiff',
        array('diffID' => head($this->getRevision()['diffs']))));

    if ($local_diff !== $reviewed_diff) {
      $diffWorkflow = $this->getWorkflow()->buildChildWorkflow('diff', array());
      $err = $diffWorkflow->run();
      if ($err) {
        $this->writeInfo("ARC_DIFF_ERROR", "arc diff failed with error.code=", $err);
        throw new ArcanistUserAbortException();
      }
      $this->setRevision($this->getWorkflow()->uberGetRevision());
    }
  }

  private function normalizeDiff($text) {
    $changes = id(new ArcanistDiffParser())->parseDiff($text);
    ksort($changes);
    return ArcanistBundle::newFromChanges($changes)->toGitPatch();
  }

  private $submitQueueClient;
  private $conduit;
}
