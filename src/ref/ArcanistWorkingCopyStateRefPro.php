<?php

final class ArcanistWorkingCopyStateRefPro
  extends ArcanistRefPro {

  const HARDPOINT_COMMITREF = 'commitRef';
  const HARDPOINT_BRANCHREF = 'branchRef';
  const HARDPOINT_REVISIONREFS = 'revisionRefs';

  public function getRefDisplayName() {
    // TODO: This could check attached hardpoints and render something more
    // insightful.
    return pht('Working Copy State');
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMITREF),
      $this->newHardpoint(self::HARDPOINT_BRANCHREF),
      $this->newVectorHardpoint(self::HARDPOINT_REVISIONREFS),
    );
  }

  public function attachBranchRef(ArcanistBranchRef $branch_ref) {
    return $this->attachHardpoint(self::HARDPOINT_BRANCHREF, $branch_ref);
  }

  public function getBranchRef() {
    return $this->getHardpoint(self::HARDPOINT_BRANCHREF);
  }

  public function setCommitRef(ArcanistCommitRef $commit_ref) {
    return $this->attachHardpoint(self::HARDPOINT_COMMITREF, $commit_ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint(self::HARDPOINT_COMMITREF);
  }

  public function getRevisionRefs() {
    return $this->getHardpoint(self::HARDPOINT_REVISIONREFS);
  }

  public function getRevisionRef() {
    if ($this->hasAmbiguousRevisionRefs()) {
      throw new Exception(
        pht('State has multiple ambiguous revisions refs.'));
    }

    $refs = $this->getRevisionRefs();
    if ($refs) {
      return head($refs);
    }

    return null;
  }

  public function hasAmbiguousRevisionRefs() {
    return (count($this->getRevisionRefs()) > 1);
  }

}
