<?php

final class ArcanistGitWorkingCopyRevisionHardpointQuery
  extends ArcanistWorkflowGitHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistWorkingCopyStateRef::HARDPOINT_COMMITREF,
      ));

    $hashes = array();
    $map = array();
    foreach ($refs as $ref_key => $ref) {
      $commit = $ref->getCommitRef();

      $commit_hashes = array();

      $commit_hashes[] = array(
        'gtcm',
        $commit->getCommitHash(),
      );

      if ($commit->getTreeHash()) {
        $commit_hashes[] = array(
          'gttr',
          $commit->getTreeHash(),
        );
      }

      foreach ($commit_hashes as $hash) {
        $hashes[] = $hash;
        $hash_key = $this->getHashKey($hash);
        $map[$hash_key][$ref_key] = $ref;
      }
    }

    $results = array_fill_keys(array_keys($refs), array());
    if ($hashes) {
      $revisions = (yield $this->yieldConduit(
        'differential.query',
        array(
          'commitHashes' => $hashes,
        )));

      foreach ($revisions as $dict) {
        $revision_hashes = idx($dict, 'hashes');
        if (!$revision_hashes) {
          continue;
        }

        $revision_ref = ArcanistRevisionRef::newFromConduitQuery($dict);
        foreach ($revision_hashes as $revision_hash) {
          $hash_key = $this->getHashKey($revision_hash);
          $state_refs = idx($map, $hash_key, array());
          foreach ($state_refs as $ref_key => $state_ref) {
            $results[$ref_key][] = $revision_ref;
          }
        }
      }
    }

    yield $this->yieldMap($results);
  }

  private function getHashKey(array $hash) {
    return $hash[0].':'.$hash[1];
  }

}
