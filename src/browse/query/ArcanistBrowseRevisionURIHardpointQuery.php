<?php

final class ArcanistBrowseRevisionURIHardpointQuery
  extends ArcanistBrowseURIHardpointQuery {

  const BROWSETYPE = 'revision';

  public function loadHardpoint(array $refs, $hardpoint) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      yield $this->yieldMap(array());
    }

    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistBrowseRef::HARDPOINT_COMMITREFS,
      ));

    $states = array();
    $map = array();
    foreach ($refs as $key => $ref) {
      foreach ($ref->getCommitRefs() as $commit_ref) {
        $hash = $commit_ref->getCommitHash();
        $states[$hash] = id(new ArcanistWorkingCopyStateRef())
          ->setCommitRef($commit_ref);
        $map[$hash][] = $key;
      }
    }

    if (!$states) {
      yield $this->yieldMap(array());
    }

    yield $this->yieldRequests(
      $states,
      array(
        'revisionRefs',
      ));

    $results = array();
    foreach ($states as $hash => $state) {
      foreach ($state->getRevisionRefs() as $revision) {
        if ($revision->isClosed()) {
          // Don't resolve closed revisions.
          continue;
        }

        $uri = $revision->getURI();

        foreach ($map[$hash] as $key) {
          $results[$key][] = $this->newBrowseURIRef()
            ->setURI($uri);
        }
      }
    }

    yield $this->yieldMap($results);
  }


}
