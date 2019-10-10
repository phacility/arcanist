<?php

final class ICFlowFeature extends Phobject {

  private $head;
  private $differentialCommitMessage;
  private $revision;
  private $search;
  private $activeDiff;

  private function __construct() {}

  public static function newFromHead(ICFlowRef $head) {
    $feature = new self();
    $feature->head = $head;
    try {
      $message = ArcanistDifferentialCommitMessage::newFromRawCorpus(
        $head->getBody());
      $feature->differentialCommitMessage = $message;
    } catch (ArcanistUsageException $e) {
      $feature->differentialCommitMessage = null;
    }
    return $feature;
  }

  public function getRevisionField($index, $default = null) {
    if (!$this->revision) {
      return null;
    }
    return idx($this->revision, $index, $default);
  }

  public function attachRevisionData(array $revision = null) {
    $this->revision = $revision;
    return $this;
  }

  public function getActiveDiffID() {
    $diffs = $this->getRevisionField('diffs');
    return $diffs ? head($diffs) : null;
  }

  public function getActiveDiffPHID() {
    return $this->getRevisionField('activeDiffPHID');
  }

  public function getRevisionStatusName() {
    return $this->getRevisionField('statusName', '');
  }

  public function getAuthorPHID() {
    return $this->getRevisionField('authorPHID');
  }

  public function getRevisionPHID() {
    return $this->getRevisionField('phid');
  }

  public function getRevisionID() {
    if (!$this->differentialCommitMessage) {
      return null;
    }
    return $this->differentialCommitMessage->getRevisionID();
  }

  public function getSearchField($index, $default = null) {
    if (!$this->search) {
      return $default;
    }
    return idx($this->search, $index, $default);
  }

  public function attachSearchData(array $search = null) {
    $this->search = $search;
    return $this;
  }

  public function getSearchAttachment($name) {
    $attachments = $this->getSearchField('attachments', array());
    return idx($attachments, $name);
  }

  public function getDifferentialCommitMessage() {
    return $this->differentialCommitMessage;
  }

  public function getName() {
    return $this->head->getName();
  }

  public function getHead() {
    return $this->head;
  }

  public function attachActiveDiff($diff) {
    $this->activeDiff = $diff;
    return $this;
  }

  public function getActiveDiff() {
    return $this->activeDiff;
  }

}
