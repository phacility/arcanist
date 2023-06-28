<?php

final class ArcanistMercurialCommitMessageHardpointQuery
  extends ArcanistWorkflowMercurialHardpointQuery {

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

    // TODO: Batch this properly and make it future oriented.

    $messages = array();
    foreach ($unique_hashes as $unique_hash) {
      $messages[$unique_hash] = $api->getCommitMessage($unique_hash);
    }

    foreach ($hashes as $ref_key => $hash) {
      $hashes[$ref_key] = $messages[$hash];
    }

    yield $this->yieldMap($hashes);
  }

}
