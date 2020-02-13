<?php

final class ArcanistBrowseCommitURIHardpointLoader
  extends ArcanistBrowseURIHardpointLoader {

  const LOADERKEY = 'browse.uri.commit';
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

  public function loadHardpoints(array $refs, $hardpoint) {
    $query = $this->getQuery();

    $api = $query->getRepositoryAPI();
    if (!$api) {
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

    $commit_refs = array();
    foreach ($refs as $key => $ref) {
      foreach ($ref->getCommitRefs() as $commit_ref) {
        $commit_refs[] = $commit_ref;
      }
    }

    $this->newQuery($commit_refs)
      ->needHardpoints(
        array(
          'upstream',
        ))
      ->execute();

    $results = array();
    foreach ($refs as $key => $ref) {
      $commit_refs = $ref->getCommitRefs();
      foreach ($commit_refs as $commit_ref) {
        $uri = $commit_ref->getURI();
        if ($uri !== null) {
          $results[$key][] = id(new ArcanistBrowseURIRef())
            ->setURI($uri)
            ->setType(self::BROWSETYPE);
        }
      }
    }

    return $results;
  }


}
