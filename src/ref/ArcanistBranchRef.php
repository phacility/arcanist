<?php

final class ArcanistBranchRef
  extends ArcanistRef {

  private $branchName;
  private $refName;
  private $isCurrentBranch;

  public function getRefIdentifier() {
    return pht('Branch %s', $this->getBranchName());
  }

  public function defineHardpoints() {
    return array(
      'commitRef' => array(
        'type' => 'ArcanistCommitRef',
      ),
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
    return $this->attachHardpoint('commitRef', $ref);
  }

  public function getCommitRef() {
    return $this->getHardpoint('commitRef');
  }

}
