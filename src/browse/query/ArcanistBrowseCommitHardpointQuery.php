<?php

final class ArcanistBrowseCommitHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistBrowseRef::HARDPOINT_COMMITREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBrowseRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $api = $this->getRepositoryAPI();

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
      yield $this->yieldMap(array());
    }

    $results = array();
    foreach ($commit_map as $commit_identifier => $ref_keys) {
      foreach ($ref_keys as $key) {
        $commit_ref = id(new ArcanistCommitRef())
          ->setCommitHash($commit_identifier);
        $results[$key][] = $commit_ref;
      }
    }

    yield $this->yieldMap($results);
  }

}
