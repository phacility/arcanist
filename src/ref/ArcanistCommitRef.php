<?php

final class ArcanistCommitRef
  extends ArcanistRef {

  private $commitHash;
  private $treeHash;
  private $commitEpoch;
  private $authorEpoch;

  public function getRefIdentifier() {
    return pht('Commit %s', $this->getCommitHash());
  }

  public function defineHardpoints() {
    return array(
      'message' => array(
        'type' => 'string',
      ),
    );
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

  public function setCommitEpoch($commit_epoch) {
    $this->commitEpoch = $commit_epoch;
    return $this;
  }

  public function getCommitEpoch() {
    return $this->commitEpoch;
  }

  public function setAuthorEpoch($author_epoch) {
    $this->authorEpoch = $author_epoch;
    return $this;
  }

  public function getAuthorEpoch() {
    return $this->authorEpoch;
  }

  public function getSummary() {
    $message = $this->getMessage();

    $message = trim($message);
    $lines = phutil_split_lines($message, false);

    return head($lines);
  }

  public function attachMessage($message) {
    return $this->attachHardpoint('message', $message);
  }

  public function getMessage() {
    return $this->getHardpoint('message');
  }

}
