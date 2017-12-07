<?php

final class UberArcanistSubmitQueueEngine
    extends ArcanistGitLandEngine
{
  private $revision;
  private $shouldShadow;
  private $skipUpdateWorkingCopy;
  private $submitQueueRegex;
  private $tbr;
  private $submitQueueTags;

  public function execute() {
    $this->verifySourceAndTargetExist();

    $workflow = $this->getWorkflow();
    if ($workflow->getConfigFromAnySource("uber.land.submitqueue.events.prepush")) {
      // fn dispatches ArcanistEventType::TYPE_LAND_WILLPUSHREVISION for
      // arc-pre-push hooks;  see UberArcPrePushEventListener
      $workflow->didCommitMerge();
    }

    $this->saveLocalState();

    try {
      $this->identifyRevision();
      assert(!empty($this->revision));
      $this->printLandingCommits();

      if ($this->getShouldPreview()) {
        $this->writeInfo(
          pht('PREVIEW'),
          pht('Completed preview of operation.'));
        return;
      }
      if (!$this->getSkipUpdateWorkingCopy()) {
        $this->updateWorkingCopy();
      }

      if ($this->getTbr()) {
        $this->uberCreateTask($this->getRevision());
        $this->pushChange();
      } else if ($this->uberShouldRunSubmitQueue($this->getRevision(), $this->submitQueueRegex)) {
        $this->pushChangeToSubmitQueue();
      } else {
        $this->pushChange();
      }

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

  private function identifyRevision() {
    if (empty($this->getRevision())) {
      $api = $this->getRepositoryAPI();
      $api->execxLocal('checkout %s --', $this->getSourceRef());
      if (!empty($this->getBuildMessageCallback())) {
        call_user_func($this->getBuildMessageCallback(), $this);
      } else {
        $message = pht(
          "Revision and callback empty");
        throw new ArcanistUsageException($message);
      }
    }
  }

  private function pushChangeToSubmitQueue() {
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
      $this->shouldShadow,
      $this->getTargetOnto());
    $this->writeInfo(
      pht('Successfully submitted the request to the Submit Queue.'),
      pht('Please use "%s" to track your changes', $statusUrl));
  }

  public function __construct($submitQueueClient, $conduit) {
    $this->submitQueueClient = $submitQueueClient;
    $this->conduit = $conduit;
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

  final public function getSkipUpdateWorkingCopy() {
    return $this->skipUpdateWorkingCopy;
  }

  final public function setSkipUpdateWorkingCopy($skipUpdateWorkingCopy) {
    $this->skipUpdateWorkingCopy = $skipUpdateWorkingCopy;
    return $this;
  }

  public function getConduit() {
    return $this->conduit;
  }

  public function getUserName() {
    return $this->userName;
  }

  public function getSubmitQueueRegex() {
    return $this->submitQueueRegex;
  }

  public function setSubmitQueueRegex($submitQueueRegex) {
    $this->submitQueueRegex = $submitQueueRegex;
    return $this;
  }

  public function getTbr() {
    if (empty($this->tbr)) {
      return false;
    }
    return $this->tbr;
  }

  public function setTbr($tbr) {
    $this->tbr = $tbr;
    return $this;
  }

  public function getSubmitQueueTags() {
    return $this->submitQueueTags;
  }

  public function setSubmitQueueTags($submitQueueTags) {
    $this->submitQueueTags = $submitQueueTags;
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

  private function uberShouldRunSubmitQueue($revision, $regex) {
    if (empty($regex)) {
      return true;
    }

    $diff = head(
      $this->getConduit()->callMethodSynchronous(
        'differential.querydiffs',
        array('ids' => array(head($revision['diffs'])))));
    $changes = array();
    foreach ($diff['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }

    foreach ($changes as $change) {
      if (preg_match($regex, $change->getOldPath())) {
        return true;
      }

      if (preg_match($regex, $change->getCurrentPath())) {
        return true;
      }
    }

    return false;
  }

  private function uberTbrGetExcuse($prompt, $history) {
    $console = PhutilConsole::getConsole();
    $history = $this->getRepositoryAPI()->getScratchFilePath($history);
    $excuse = phutil_console_prompt($prompt, $history);
    if ($excuse == '') {
      throw new ArcanistUserAbortException();
    }
    return $excuse;
  }

  private function uberCreateTask($revision) {
    if (empty($this->getSubmitQueueTags())) {
      return;
    }

    $console = PhutilConsole::getConsole();
    $excuse = $this->uberTbrGetExcuse(
      pht('Provide explanation for skipping SubmitQueue or press Enter to abort.'),
      'tbr-excuses');
    $args = array(
      pht('%s is skipping SubmitQueue', 'D' . $revision['id']),
      '--uber-description',
      pht("%s is skipping SubmitQueue\n Author: %s\n Excuse: %s\n",
        'D' . $revision['id'],
        $this->getWorkflow()->getUserName(),
        $excuse),
      '--browse');
    foreach ($this->submitQueueTags as $tag) {
      array_push($args, "--project", $tag);
    }

    $owners = $this->getWorkflow()->getConfigFromAnySource("uber.land.submitqueue.owners");
    foreach ($owners as $owner) {
      array_push($args, "--cc", $owner);
    }

    $todo_workflow = $this->getWorkflow()->buildChildWorkflow('todo', $args);
    $todo_workflow->run();
  }

  protected function getLandingCommits() {
    if ($this->getRevision()) {
      $diff = head(
        $this->getConduit()->callMethodSynchronous(
          'differential.querydiffs',
          array('ids' => array(head($this->getRevision()['diffs'])))));
      $properties = idx($diff, 'properties', array());
      $commits = idx($properties, 'local:commits', array());
      return ipull($commits, 'summary');
    } else {
      return array();
    }
  }

  private $submitQueueClient;
  private $conduit;
}
