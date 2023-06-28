<?php

final class ArcanistGitCommitMessageHardpointQuery
  extends ArcanistWorkflowGitHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistCommitRef::HARDPOINT_MESSAGE,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistCommitRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $api = $this->getRepositoryAPI();

    $hashes = mpull($refs, 'getCommitHash');
    $unique_hashes = array_fuse($hashes);

    // TODO: Update this to use "%B", see T5028. We can also bulk-resolve
    // these with "git show --quiet --format=... hash hash hash ... --".

    $futures = array();
    foreach ($unique_hashes as $hash) {
      $futures[$hash] = $api->execFutureLocal(
        'log -n1 --format=%s %s --',
        '%s%n%n%b',
        gitsprintf('%s', $hash));
    }

    yield $this->yieldFutures($futures);

    $messages = array();
    foreach ($futures as $hash => $future) {
      list($stdout) = $future->resolvex();
      $messages[$hash] = $stdout;
    }

    foreach ($hashes as $ref_key => $hash) {
      $hashes[$ref_key] = $messages[$hash];
    }

    yield $this->yieldMap($hashes);
  }

}
