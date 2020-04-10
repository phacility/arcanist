<?php

final class ArcanistBrowseCommitURIHardpointQuery
  extends ArcanistBrowseURIHardpointQuery {

  const BROWSETYPE = 'commit';

  public function willLoadBrowseURIRefs(array $refs) {
    $refs = $this->getRefsWithSupportedTypes($refs);

    if (!$refs) {
      return;
    }

    $query = $this->getQuery();

    $working_ref = $query->getWorkingCopyRef();
    if (!$working_ref) {
      // If we aren't in a working copy, don't warn about this.
      return;
    }

    $repository_ref = $this->getQuery()->getRepositoryRef();
    if (!$repository_ref) {
      echo pht(
        'NO REPOSITORY: Unable to determine which repository this working '.
        'copy belongs to, so arguments can not be resolved as commits. Use '.
        '"%s" to understand how repositories are resolved.',
        'arc which');
      echo "\n";
      return;
    }
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      yield $this->yieldMap(array());
    }

    yield $this->yieldRequests(
      $refs,
      array(
        ArcanistBrowseRefPro::HARDPOINT_COMMITREFS,
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
        ArcanistCommitRefPro::HARDPOINT_UPSTREAM,
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
