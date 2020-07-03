<?php

final class ArcanistCommitNode
  extends Phobject {

  private $commitHash;
  private $childNodes = array();
  private $parentNodes = array();
  private $commitRef;
  private $commitMessage;
  private $commitEpoch;

  public function setCommitHash($commit_hash) {
    $this->commitHash = $commit_hash;
    return $this;
  }

  public function getCommitHash() {
    return $this->commitHash;
  }

  public function addChildNode(ArcanistCommitNode $node) {
    $this->childNodes[$node->getCommitHash()] = $node;
    return $this;
  }

  public function setChildNodes(array $nodes) {
    $this->childNodes = $nodes;
    return $this;
  }

  public function getChildNodes() {
    return $this->childNodes;
  }

  public function addParentNode(ArcanistCommitNode $node) {
    $this->parentNodes[$node->getCommitHash()] = $node;
    return $this;
  }

  public function setParentNodes(array $nodes) {
    $this->parentNodes = $nodes;
    return $this;
  }

  public function getParentNodes() {
    return $this->parentNodes;
  }

  public function setCommitMessage($commit_message) {
    $this->commitMessage = $commit_message;
    return $this;
  }

  public function getCommitMessage() {
    return $this->commitMessage;
  }

  public function getCommitRef() {
    if ($this->commitRef === null) {
      $this->commitRef = id(new ArcanistCommitRef())
        ->setCommitHash($this->getCommitHash())
        ->attachMessage($this->getCommitMessage());
    }

    return $this->commitRef;
  }

  public function setCommitEpoch($commit_epoch) {
    $this->commitEpoch = $commit_epoch;
    return $this;
  }

  public function getCommitEpoch() {
    return $this->commitEpoch;
  }

}
