<?php

final class ArcanistBrowseCommitURIHardpointQuery
  extends ArcanistBrowseURIHardpointQuery {

  const BROWSETYPE = 'commit';

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

    $commit_refs = array();
    foreach ($refs as $key => $ref) {
      foreach ($ref->getCommitRefs() as $commit_ref) {
        $commit_refs[] = $commit_ref;
      }
    }

    yield $this->yieldRequests(
      $commit_refs,
      array(
        ArcanistCommitRef::HARDPOINT_UPSTREAM,
      ));

    $results = array();
    foreach ($refs as $key => $ref) {
      $commit_refs = $ref->getCommitRefs();
      foreach ($commit_refs as $commit_ref) {
        $uri = $commit_ref->getURI();
        if ($uri !== null) {
          $results[$key][] = $this->newBrowseURIRef()
            ->setURI($uri);
        }
      }
    }

    yield $this->yieldMap($results);
  }


}
