<?php

final class ArcanistCommitRef
  extends ArcanistRef {

  private $commitHash;
  private $treeHash;
  private $commitEpoch;
  private $authorEpoch;
  private $upstream;

  const HARDPOINT_MESSAGE = 'message';
  const HARDPOINT_UPSTREAM = 'upstream';

  public function getRefDisplayName() {
    return pht('Commit "%s"', $this->getCommitHash());
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_MESSAGE),
      $this->newHardpoint(self::HARDPOINT_UPSTREAM),
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
    return $this->attachHardpoint(self::HARDPOINT_MESSAGE, $message);
  }

  public function getMessage() {
    return $this->getHardpoint(self::HARDPOINT_MESSAGE);
  }

  public function getURI() {
    return $this->getUpstreamProperty('uri');
  }

  private function getUpstreamProperty($key, $default = null) {
    $upstream = $this->getHardpoint(self::HARDPOINT_UPSTREAM);

    if (!$upstream) {
      return $default;
    }

    return idx($upstream, $key, $default);
  }

}
