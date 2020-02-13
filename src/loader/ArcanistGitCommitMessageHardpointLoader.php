<?php

final class ArcanistGitCommitMessageHardpointLoader
  extends ArcanistGitHardpointLoader {

  const LOADERKEY = 'git.commit.message';

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistCommitRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'message');
  }

  public function loadHardpoints(array $refs, $hardpoint) {
    $api = $this->getQuery()->getRepositoryAPI();


    $futures = array();
    foreach ($refs as $ref_key => $ref) {
      $hash = $ref->getCommitHash();

      $futures[$ref_key] = $api->execFutureLocal(
        'log -n1 --format=%C %s --',
        '%s%n%n%b',
        $hash);
    }

    $iterator = $this->newFutureIterator($futures);

    $results = array();
    foreach ($iterator as $ref_key => $future) {
      list($stdout) = $future->resolvex();
      $results[$ref_key] = $stdout;
    }

    return $results;
  }

}
