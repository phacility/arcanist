<?php

final class ArcanistMarkerRef
  extends ArcanistRef {

  const HARDPOINT_COMMITREF = 'arc.marker.commitRef';
  const HARDPOINT_WORKINGCOPYSTATEREF = 'arc.marker.workingCopyStateRef';
  const HARDPOINT_REMOTEREF = 'arc.marker.remoteRef';

  const TYPE_BRANCH = 'branch';
  const TYPE_BOOKMARK = 'bookmark';
  const TYPE_COMMIT_STATE = 'commit-state';
  const TYPE_BRANCH_STATE = 'branch-state';

  private $name;
  private $markerType;
  private $epoch;
  private $markerHash;
  private $commitHash;
  private $displayHash;
  private $treeHash;
  private $summary;
  private $message;
  private $isActive = false;
  private $remoteName;

  public function getRefDisplayName() {
    switch ($this->getMarkerType()) {
      case self::TYPE_BRANCH:
        return pht('Branch "%s"', $this->getName());
      case self::TYPE_BOOKMARK:
        return pht('Bookmark "%s"', $this->getName());
      default:
        return pht('Marker "%s"', $this->getName());
    }
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMITREF),
      $this->newHardpoint(self::HARDPOINT_WORKINGCOPYSTATEREF),
      $this->newHardpoint(self::HARDPOINT_REMOTEREF),
    );
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setMarkerType($marker_type) {
    $this->markerType = $marker_type;
    return $this;
  }

  public function getMarkerType() {
    return $this->markerType;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function getEpoch() {
    return $this->epoch;
  }

  public function setMarkerHash($marker_hash) {
    $this->markerHash = $marker_hash;
    return $this;
  }

  public function getMarkerHash() {
    return $this->markerHash;
  }

  public function setDisplayHash($display_hash) {
    $this->displayHash = $display_hash;
    return $this;
  }

  public function getDisplayHash() {
    return $this->displayHash;
  }

  public function setCommitHash($commit_hash) {
    $this->commitHash = $commit_hash;
    return $this;
  }

  public function getCommitHash() {
    return $this->commitHash;
  }

  public function setTreeHash($tree_hash) {
    $this->treeHash = $tree_hash;
    return $this;
  }

  public function getTreeHash() {
    return $this->treeHash;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function setMessage($message) {
    $this->message = $message;
    return $this;
  }

  public function getMessage() {
    return $this->message;
  }

  public function setIsActive($is_active) {
    $this->isActive = $is_active;
    return $this;
  }

  public function getIsActive() {
    return $this->isActive;
  }

  public function setRemoteName($remote_name) {
    $this->remoteName = $remote_name;
    return $this;
  }

  public function getRemoteName() {
    return $this->remoteName;
  }

  public function isBookmark() {
    return ($this->getMarkerType() === self::TYPE_BOOKMARK);
  }

  public function isBranch() {
    return ($this->getMarkerType() === self::TYPE_BRANCH);
  }

  public function isCommitState() {
    return ($this->getMarkerType() === self::TYPE_COMMIT_STATE);
  }

  public function isBranchState() {
    return ($this->getMarkerType() === self::TYPE_BRANCH_STATE);
  }

  public function attachCommitRef(ArcanistCommitRef $ref) {
    return $this->attachHardpoint(self::HARDPOINT_COMMITREF, $ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint(self::HARDPOINT_COMMITREF);
  }

  public function attachWorkingCopyStateRef(ArcanistWorkingCopyStateRef $ref) {
    return $this->attachHardpoint(self::HARDPOINT_WORKINGCOPYSTATEREF, $ref);
  }

  public function getWorkingCopyStateRef() {
    return $this->getHardpoint(self::HARDPOINT_WORKINGCOPYSTATEREF);
  }

  public function attachRemoteRef(ArcanistRemoteRef $ref = null) {
    return $this->attachHardpoint(self::HARDPOINT_REMOTEREF, $ref);
  }

  public function getRemoteRef() {
    return $this->getHardpoint(self::HARDPOINT_REMOTEREF);
  }

  protected function buildRefView(ArcanistRefView $view) {
    $title = pht(
      '%s %s',
      $this->getDisplayHash(),
      $this->getSummary());

    $view
      ->setObjectName($this->getRefDisplayName())
      ->setTitle($title);
  }

}
