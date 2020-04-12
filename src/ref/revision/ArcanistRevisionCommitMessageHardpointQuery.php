<?php

final class ArcanistRevisionCommitMessageHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRevisionRef::HARDPOINT_COMMITMESSAGE,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRevisionRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $api = $this->getRepositoryAPI();

    // NOTE: This is not efficient, but no bulk API exists at time of
    // writing and no callers bulk-load this data.

    $results = array();
    foreach ($refs as $key => $ref) {
      $message = (yield $this->yieldConduit(
        'differential.getcommitmessage',
        array(
          'revision_id' => $ref->getID(),
          'edit' => false,
        )));

      $results[$key] = $message;
    }

    yield $this->yieldMap($results);
  }

}
