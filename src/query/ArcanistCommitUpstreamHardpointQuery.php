<?php

final class ArcanistCommitUpstreamHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistCommitRef::HARDPOINT_UPSTREAM,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistCommitRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $repository_ref = (yield $this->yieldRepositoryRef());
    if (!$repository_ref) {
      yield $this->yieldValue($refs, null);
    }
    $repository_phid = $repository_ref->getPHID();

    $commit_map = array();
    foreach ($refs as $key => $ref) {
      $hash = $ref->getCommitHash();
      $commit_map[$hash][] = $key;
    }

    $commit_info = (yield $this->yieldConduit(
      'diffusion.querycommits',
      array(
        'repositoryPHID' => $repository_phid,
        'names' => array_keys($commit_map),
      )));

    $results = array();
    foreach ($commit_map as $hash => $keys) {
      $commit_phid = idx($commit_info['identifierMap'], $hash);
      if ($commit_phid) {
        $commit_data = idx($commit_info['data'], $commit_phid);
      } else {
        $commit_data = null;
      }

      foreach ($keys as $key) {
        $results[$key] = $commit_data;
      }
    }

    yield $this->yieldMap($results);
  }

}
