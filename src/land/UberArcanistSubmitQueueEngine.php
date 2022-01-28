<?php

class UberArcanistSubmitQueueEngine
    extends ArcanistGitLandEngine
{
  protected $submitQueueClient;
  protected $conduit;
  protected $revision;
  protected $shouldShadow;
  protected $skipUpdateWorkingCopy;
  private $submitQueueRegex;
  private $tbr;
  private $submitQueueTags;
  private $usesArcFlow;
  private $autolandProjectPhid = 'PHID-PROJ-u4i3446wedyolppkckbp'; // UBER CODE

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
      $this->validate();
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
    $r = $this->getRevision();
    if (empty($r)) {
      $api = $this->getRepositoryAPI();
      $api->execxLocal('checkout %s --', $this->getSourceRef());
      $c = $this->getBuildMessageCallback();
      if (!empty($c)) {
        $builds_fine = call_user_func($c, $this);
        $revision = $this->getRevision();
        if ($revision && !$builds_fine) {
          if ($this->tagWithAutoland($revision)) {
            exit(0);
          }
        }
      } else {
        $message = pht(
          "Revision and callback empty");
        throw new ArcanistUsageException($message);
      }
    }
  }

  /**
   * Validate if autoland tag should be added to the revision and actual landing
   * procedure should be stopped. Return false if tagging was skipped.
   */
  private function tagWithAutoland($revision) {
    // Check if explicit flags are provided
    if (true === $this->getTbr()) {
      return false;
    }

    // check if we have TTY otherwise it is probably automation...
    try {
      phutil_console_require_tty();
    } catch (PhutilConsoleStdinNotInteractiveException $e) {
      return false;
    }

    // do not execute check for non uber Phabricator though general
    // recommendation would be to checkout upstream arcanist
    if (strpos($this->getConduit()->getHost(), "uberinternal.com")===false) {
      return false;
    }

    // Skip if SubmitQueue is not enabled
    $workflow = $this->getWorkflow();
    $usesSubmitQueue = nonempty(
      $workflow->getConfigFromAnySource('uber.land.submitqueue.enable'),
      false
    );

    if (!$usesSubmitQueue) {
      return false;
    }

    $taggingEnabled = nonempty(
      $workflow->getConfigFromAnySource(
        'uber.differential.autoland-if-building'),
      false
    );

    if (!$taggingEnabled) {
      return false;
    }

    // Skip if summary already has #autoland or BREAKGLASS
    $summary = idx($revision, 'summary');
    if ($summary !== null) {
      $hasAutolandTag = (bool)preg_match('/\b#autoland\b/i', $summary);
      $hasBreakglass = (bool)preg_match('/\bBREAKGLASS\b/i', $summary);
      if ($hasAutolandTag || $hasBreakglass) {
        return false;
      }
    }

    $this
      ->getConduit()
      ->callMethodSynchronous('differential.revision.edit',
      array(
        'objectIdentifier' => $revision['id'],
        'transactions' => array(
          array(
            'type'  => 'projects.add',
            'value' => array($this->autolandProjectPhid),
          ),
        ),
    ));

    $this->writeInfo(
      pht('LANDING'),
      pht('There are ongoing build(s) for this revision. #autoland tag was ' .
      'added to the revision to land it automatically after builds pass.'
      )
    );
    $this->writeInfo(
      pht('LANDING'),
      pht('During emergency situation add BREAKGLASS keyword to revision '.
        'description via Phabricator UI and click "Land to SubmitQueue" link.')
    );

    return true;
  }

  protected function pushChangeToSubmitQueue() {
    $this->writeInfo(
      pht('PUSHING'),
      pht('Pushing changes to Submit Queue.'));
    $api = $this->getRepositoryAPI();
    $remoteUrl = $api->uberGetGitRemotePushUrl($this->getTargetRemote());

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

  public function __construct($submitQueueClient, $conduit, $usesArcFlow = false) {
    $this->submitQueueClient = $submitQueueClient;
    $this->conduit = $conduit;
    $this->usesArcFlow = $usesArcFlow;
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
        array('diffID' => head(idx($this->getRevision(), 'diffs')))));

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

  protected function normalizeDiff($text) {
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

  protected function getLandingCommits() {
    if ($this->getRevision()) {
      $diff = head(
        $this->getConduit()->callMethodSynchronous(
          'differential.querydiffs',
          array('ids' => array(head(idx($this->getRevision(), 'diffs'))))));
      $properties = idx($diff, 'properties', array());
      $commits = idx($properties, 'local:commits', array());
      $result = ipull($commits, 'summary');
      if (!$result) {
        return array("There are no commits on \"master\" which are not already present on the target.");
      }
      return $result;
    } else {
      return array();
    }
  }

  protected function validate() {
    assert(!empty($this->revision));
  }

  public function getUsesArcFlow() {
    return $this->usesArcFlow;
  }
}
