<?php

final class ArcanistMercurialBranchCommitHardpointLoader
  extends ArcanistMercurialHardpointLoader {

  const LOADERKEY = 'hg.branch.commit';

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBranchRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'commitRef');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $api = $this->getQuery()->getRepositoryAPI();

    $futures = array();
    foreach ($refs as $ref_key => $branch) {
      $branch_name = $branch->getBranchName();

      $futures[$ref_key] = $api->execFutureLocal(
        'log -l 1 --template %s -r %s',
        "{node}\1{date|hgdate}\1{p1node}\1{desc|firstline}\1{desc}",
        hgsprintf('%s', $branch_name));
    }

    $results = array();

    $iterator = $this->newFutureIterator($futures);
    foreach ($iterator as $ref_key => $future) {
      list($info) = $future->resolvex();

      $fields = explode("\1", trim($info), 5);
      list($hash, $epoch, $parent, $desc, $text) = $fields;

      $commit_ref = $api->newCommitRef()
        ->setCommitHash($hash)
        ->setCommitEpoch((int)$epoch)
        ->attachMessage($text);

      $results[$ref_key] = $commit_ref;
    }

    return $results;
  }

}
