<?php

final class ArcanistMessageRevisionHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistWorkingCopyStateRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistWorkingCopyStateRef::HARDPOINT_COMMITREF,
      ));

    $commit_refs = array();
    foreach ($refs as $ref) {
      $commit_refs[] = $ref->getCommitRef();
    }

    yield $this->yieldRequests(
      $commit_refs,
      array(
        ArcanistCommitRef::HARDPOINT_MESSAGE,
      ));

    $map = array();
    foreach ($refs as $ref_key => $ref) {
      $commit_ref = $ref->getCommitRef();
      $corpus = $commit_ref->getMessage();

      $id = null;
      try {
        $message = ArcanistDifferentialCommitMessage::newFromRawCorpus($corpus);
        $id = $message->getRevisionID();
      } catch (ArcanistUsageException $ex) {
        continue;
      }

      if (!$id) {
        continue;
      }

      $map[$id][$ref_key] = $ref;
    }

    $results = array();
    if ($map) {
      $revisions = (yield $this->yieldConduitSearch(
        'differential.revision.search',
        array(
          'ids' => array_keys($map),
        )));

      foreach ($revisions as $dict) {
        $revision_ref = ArcanistRevisionRef::newFromConduit($dict);
        $id = $dict['id'];

        $state_refs = idx($map, $id, array());
        foreach ($state_refs as $ref_key => $state_ref) {
          $results[$ref_key][] = $revision_ref;
        }
      }
    }

    yield $this->yieldMap($results);
  }

}
