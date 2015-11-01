<?php

abstract class ArcanistLandEngine extends Phobject {

  private $workflow;
  private $repositoryAPI;
  private $targetRemote;
  private $targetOnto;
  private $sourceRef;
  private $commitMessageFile;
  private $shouldHold;
  private $shouldKeep;
  private $shouldSquash;
  private $shouldDeleteRemote;
  private $shouldPreview;

  // TODO: This is really grotesque.
  private $buildMessageCallback;

  final public function setWorkflow(ArcanistWorkflow $workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  final public function getWorkflow() {
    return $this->workflow;
  }

  final public function setRepositoryAPI(
    ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  final public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  final public function setShouldHold($should_hold) {
    $this->shouldHold = $should_hold;
    return $this;
  }

  final public function getShouldHold() {
    return $this->shouldHold;
  }

  final public function setShouldKeep($should_keep) {
    $this->shouldKeep = $should_keep;
    return $this;
  }

  final public function getShouldKeep() {
    return $this->shouldKeep;
  }

  final public function setShouldSquash($should_squash) {
    $this->shouldSquash = $should_squash;
    return $this;
  }

  final public function getShouldSquash() {
    return $this->shouldSquash;
  }

  final public function setShouldPreview($should_preview) {
    $this->shouldPreview = $should_preview;
    return $this;
  }

  final public function getShouldPreview() {
    return $this->shouldPreview;
  }

  final public function setTargetRemote($target_remote) {
    $this->targetRemote = $target_remote;
    return $this;
  }

  final public function getTargetRemote() {
    return $this->targetRemote;
  }

  final public function setTargetOnto($target_onto) {
    $this->targetOnto = $target_onto;
    return $this;
  }

  final public function getTargetOnto() {
    return $this->targetOnto;
  }

  final public function setSourceRef($source_ref) {
    $this->sourceRef = $source_ref;
    return $this;
  }

  final public function getSourceRef() {
    return $this->sourceRef;
  }

  final public function setBuildMessageCallback($build_message_callback) {
    $this->buildMessageCallback = $build_message_callback;
    return $this;
  }

  final public function getBuildMessageCallback() {
    return $this->buildMessageCallback;
  }

  final public function setCommitMessageFile($commit_message_file) {
    $this->commitMessageFile = $commit_message_file;
    return $this;
  }

  final public function getCommitMessageFile() {
    return $this->commitMessageFile;
  }

  abstract public function execute();

  abstract protected function getLandingCommits();

  protected function printLandingCommits() {
    $logs = $this->getLandingCommits();

    if (!$logs) {
      throw new ArcanistUsageException(
        pht(
          'There are no commits on "%s" which are not already present on '.
          'the target.',
          $this->getSourceRef()));
    }

    $list = id(new PhutilConsoleList())
      ->setWrap(false)
      ->addItems($logs);

    id(new PhutilConsoleBlock())
      ->addParagraph(
        pht(
          'These %s commit(s) will be landed:',
          new PhutilNumber(count($logs))))
      ->addList($list)
      ->draw();
  }

  protected function writeWarn($title, $message) {
    return $this->getWorkflow()->writeWarn($title, $message);
  }

  protected function writeInfo($title, $message) {
    return $this->getWorkflow()->writeInfo($title, $message);
  }

  protected function writeOkay($title, $message) {
    return $this->getWorkflow()->writeOkay($title, $message);
  }


}
