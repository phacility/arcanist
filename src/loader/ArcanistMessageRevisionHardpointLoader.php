<?php

final class ArcanistMessageRevisionHardpointLoader
  extends ArcanistHardpointLoader {

  const LOADERKEY = 'message.revision';

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return true;
  }

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

    $commit_refs = array();
    foreach ($refs as $ref) {
      $commit_refs[] = $ref->getCommitRef();
    }

    $this->newQuery($commit_refs)
      ->needHardpoints(
        array(
          'message',
        ))
      ->execute();

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
      $revisions = $this->resolveCall(
        'differential.query',
        array(
          'ids' => array_keys($map),
        ));

      foreach ($revisions as $dict) {
        $revision_ref = ArcanistRevisionRef::newFromConduit($dict);
        $id = $dict['id'];

        $state_refs = idx($map, $id, array());
        foreach ($state_refs as $ref_key => $state_ref) {
          $results[$ref_key][] = $revision_ref;
        }
      }
    }

    return $results;
  }

}
