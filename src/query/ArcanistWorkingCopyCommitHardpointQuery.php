<?php

final class ArcanistWorkingCopyCommitHardpointQuery
  extends ArcanistWorkflowHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistWorkingCopyStateRefPro::HARDPOINT_COMMITREF,
    );
  }

  protected function canLoadRef(ArcanistRefPro $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRefPro);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistWorkingCopyStateRefPro::HARDPOINT_BRANCHREF,
      ));

    $branch_refs = mpull($refs, 'getBranchRef');

    yield $this->yieldRequests(
      $branch_refs,
      array(
        ArcanistBranchRefPro::HARDPOINT_COMMITREF,
      ));

    $results = array();
    foreach ($refs as $key => $ref) {
      $results[$key] = $ref->getBranchRef()->getCommitRef();
    }

    yield $this->yieldMap($results);
  }

}
