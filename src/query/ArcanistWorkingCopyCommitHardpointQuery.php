<?php

final class ArcanistWorkingCopyCommitHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistWorkingCopyStateRef::HARDPOINT_COMMITREF,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistWorkingCopyStateRef::HARDPOINT_BRANCHREF,
      ));

    $branch_refs = mpull($refs, 'getBranchRef');

    yield $this->yieldRequests(
      $branch_refs,
      array(
        ArcanistBranchRef::HARDPOINT_COMMITREF,
      ));

    $results = array();
    foreach ($refs as $key => $ref) {
      $results[$key] = $ref->getBranchRef()->getCommitRef();
    }

    yield $this->yieldMap($results);
  }

}
