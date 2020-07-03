<?php

final class ArcanistLandCommitSet
  extends Phobject {

  private $revisionRef;
  private $commits;
  private $isPick;

  public function setRevisionRef(ArcanistRevisionRef $revision_ref) {
    $this->revisionRef = $revision_ref;
    return $this;
  }

  public function getRevisionRef() {
    return $this->revisionRef;
  }

  public function setCommits(array $commits) {
    assert_instances_of($commits, 'ArcanistLandCommit');
    $this->commits = $commits;

    $revision_phid = $this->getRevisionRef()->getPHID();
    foreach ($commits as $commit) {
      $revision_ref = $commit->getExplicitRevisionRef();

      if ($revision_ref) {
        if ($revision_ref->getPHID() === $revision_phid) {
          continue;
        }
      }

      $commit->setIsImplicitCommit(true);
    }

    return $this;
  }

  public function getCommits() {
    return $this->commits;
  }

  public function hasImplicitCommits() {
    foreach ($this->commits as $commit) {
      if ($commit->getIsImplicitCommit()) {
        return true;
      }
    }

    return false;
  }

  public function hasDirectSymbols() {
    foreach ($this->commits as $commit) {
      if ($commit->getDirectSymbols()) {
        return true;
      }
    }

    return false;
  }

  public function setIsPick($is_pick) {
    $this->isPick = $is_pick;
    return $this;
  }

  public function getIsPick() {
    return $this->isPick;
  }

}
