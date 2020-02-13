<?php

final class ArcanistCommitRef
  extends ArcanistRef {

  private $commitHash;
  private $treeHash;
  private $commitEpoch;
  private $authorEpoch;
  private $upstream;

  public function getRefIdentifier() {
    return pht('Commit %s', $this->getCommitHash());
  }

  public function defineHardpoints() {
    return array(
      'message' => array(
        'type' => 'string',
      ),
      'upstream' => array(
        'type' => 'wild',
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

  public function getURI() {
    return $this->getUpstreamProperty('uri');
  }

  private function getUpstreamProperty($key, $default = null) {
    $upstream = $this->getHardpoint('upstream');

    if (!$upstream) {
      return $default;
    }

    return idx($upstream, $key, $default);
  }

}
