<?php

final class ArcanistBranchRefPro
  extends ArcanistRefPro {

  const HARDPOINT_COMMITREF = 'commitRef';

  private $branchName;
  private $refName;
  private $isCurrentBranch;

  public function getRefDisplayName() {
    return pht('Branch %s', $this->getBranchName());
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMITREF),
    );
  }

  public function setBranchName($branch_name) {
    $this->branchName = $branch_name;
    return $this;
  }

  public function getBranchName() {
    return $this->branchName;
  }

  public function setRefName($ref_name) {
    $this->refName = $ref_name;
    return $this;
  }

  public function getRefName() {
    return $this->refName;
  }

  public function setIsCurrentBranch($is_current_branch) {
    $this->isCurrentBranch = $is_current_branch;
    return $this;
  }

  public function getIsCurrentBranch() {
    return $this->isCurrentBranch;
  }

  public function attachCommitRef(ArcanistCommitRef $ref) {
    return $this->attachHardpoint(self::HARDPOINT_COMMITREF, $ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint(self::HARDPOINT_COMMITREF);
  }

}
