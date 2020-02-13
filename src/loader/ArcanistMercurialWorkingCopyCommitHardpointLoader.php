<?php

final class ArcanistMercurialWorkingCopyCommitHardpointLoader
  extends ArcanistMercurialHardpointLoader {

  const LOADERKEY = 'hg.state.commit';

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'commitRef');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $branch_refs = array();
    foreach ($refs as $ref_key => $ref) {
      if ($ref->hasAttachedHardpoint('branchRef')) {
        $branch_refs[$ref_key] = $ref->getBranchRef();
      }
    }

    if ($branch_refs) {
      $this->newQuery($branch_refs)
        ->needHardpoints(
          array(
            'commitRef',
          ))
        ->execute();
    }

    return mpull($branch_refs, 'getCommitRef');
  }

}
