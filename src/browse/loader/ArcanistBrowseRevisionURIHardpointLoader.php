<?php

final class ArcanistBrowseRevisionURIHardpointLoader
  extends ArcanistBrowseURIHardpointLoader {

  const LOADERKEY = 'browse.uri.revision';
  const BROWSETYPE = 'revision';

  public function loadHardpoints(array $refs, $hardpoint) {
    $query = $this->getQuery();

    $working_ref = $query->getWorkingCopyRef();
    if (!$working_ref) {
      return array();
    }

    $repository_ref = $query->getRepositoryRef();
    if (!$repository_ref) {
      return array();
    }

    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      return array();
    }

    $this->newQuery($refs)
      ->needHardpoints(
        array(
          'commitRefs',
        ))
      ->execute();

    $states = array();
    $map = array();
    foreach ($refs as $key => $ref) {
      foreach ($ref->getCommitRefs() as $commit_ref) {
        $hash = $commit_ref->getCommitHash();
        $states[$hash] = id(clone $working_ref)
          ->setCommitRef($commit_ref);
        $map[$hash][] = $key;
      }
    }

    if (!$states) {
      return array();
    }

    $this->newQuery($states)
      ->needHardpoints(
        array(
          'revisionRefs',
        ))
      ->execute();

    $results = array();
    foreach ($states as $hash => $state) {
      foreach ($state->getRevisionRefs() as $revision) {
        if ($revision->isClosed()) {
          // Don't resolve closed revisions.
          continue;
        }

        $uri = $revision->getURI();

        foreach ($map[$hash] as $key) {
          $results[$key][] = id(new ArcanistBrowseURIRef())
            ->setURI($uri)
            ->setType(self::BROWSETYPE);
        }
      }
    }

    return $results;
  }


}
