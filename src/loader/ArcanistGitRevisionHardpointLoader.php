<?php

final class ArcanistGitRevisionHardpointLoader
  extends ArcanistGitHardpointLoader {

  const LOADERKEY = 'git.revision';

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'revisionRefs');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $this->newQuery($refs)
      ->needHardpoints(
        array(
          'commitRef',
        ))
      ->execute();

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

    $results = array();
    if ($hashes) {
      $revisions = $this->resolveCall(
        'differential.query',
        array(
          'commitHashes' => $hashes,
        ));

      foreach ($revisions as $dict) {
        $revision_hashes = idx($dict, 'hashes');
        if (!$revision_hashes) {
          continue;
        }

        $revision_ref = ArcanistRevisionRef::newFromConduit($dict);
        foreach ($revision_hashes as $revision_hash) {
          $hash_key = $this->getHashKey($revision_hash);
          $state_refs = idx($map, $hash_key, array());
          foreach ($state_refs as $ref_key => $state_ref) {
            $results[$ref_key][] = $revision_ref;
          }
        }
      }
    }

    return $results;
  }

  private function getHashKey(array $hash) {
    return $hash[0].':'.$hash[1];
  }

}
