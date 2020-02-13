<?php

final class ArcanistBrowseCommitHardpointLoader
  extends ArcanistHardpointLoader {

  const LOADERKEY = 'browse.ref.commit';

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return true;
  }

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBrowseRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'commitRefs');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $query = $this->getQuery();

    $api = $query->getRepositoryAPI();
    if (!$api) {
      return array();
    }

    $repository_ref = $query->getRepositoryRef();
    if (!$repository_ref) {
      return array();
    }
    $repository_phid = $repository_ref->getPHID();

    $commit_map = array();
    foreach ($refs as $key => $ref) {
      $token = $ref->getToken();

      if ($token === '.') {
        // Git resolves "." like HEAD, but we want to treat it as "browse the
        // current directory" instead in all cases.
        continue;
      }

      // Always resolve the empty token; top-level loaders filter out
      // irrelevant tokens before this stage.
      if ($token === null) {
        $token = $api->getHeadCommit();
      }

      // TODO: We should pull a full commit ref out of the API as soon as it
      // is able to provide them. In particular, we currently miss Git tree
      // hashes which reduces the accuracy of lookups.

      try {
        $commit = $api->getCanonicalRevisionName($token);
        if ($commit) {
          $commit_map[$commit][] = $key;
        }
      } catch (Exception $ex) {
        // Ignore anything we can't resolve.
      }
    }

    if (!$commit_map) {
      return array();
    }

    $results = array();
    foreach ($commit_map as $commit_identifier => $ref_keys) {
      foreach ($ref_keys as $key) {
        $commit_ref = id(new ArcanistCommitRef())
          ->setCommitHash($commit_identifier);
        $results[$key][] = $commit_ref;
      }
    }

    return $results;
  }

}
