<?php

final class ArcanistMarkerRef
  extends ArcanistRef {

  const HARDPOINT_COMMITREF = 'commitRef';

  const TYPE_BRANCH = 'branch';
  const TYPE_BOOKMARK = 'bookmark';

  private $name;
  private $markerType;
  private $epoch;
  private $markerHash;
  private $commitHash;
  private $treeHash;
  private $summary;
  private $message;
  private $isActive = false;

  public function getRefDisplayName() {
    return pht('Marker %s', $this->getName());
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMITREF),
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

  public function attachCommitRef(ArcanistCommitRef $ref) {
    return $this->attachHardpoint(self::HARDPOINT_COMMITREF, $ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint(self::HARDPOINT_COMMITREF);
  }

}
