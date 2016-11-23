<?php

final class ArcanistCommitUpstreamHardpointLoader
  extends ArcanistHardpointLoader {

  const LOADERKEY = 'commit.conduit';

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return true;
  }

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistCommitRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'upstream');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $query = $this->getQuery();

    $repository_ref = $query->getRepositoryRef();
    if (!$repository_ref) {
      return array_fill_keys(array_keys($refs), null);
    }
    $repository_phid = $repository_ref->getPHID();

    $commit_map = array();
    foreach ($refs as $key => $ref) {
      $hash = $ref->getCommitHash();
      $commit_map[$hash][] = $key;
    }

    $commit_info = $this->resolveCall(
      'diffusion.querycommits',
      array(
        'repositoryPHID' => $repository_phid,
        'names' => array_keys($commit_map),
      ));

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

    return $results;
  }

}
