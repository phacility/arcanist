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
    $api = $this->getQuery()->getRepositoryAPI();
    if (!$api) {
      return array();
    }

    $repository_ref = $this->getQuery()->getRepositoryRef();
    if (!$repository_ref) {
      return array();
    }

    $repository_phid = $repository_ref->getPHID();

    $refs = $this->getRefsWithSupportedTypes($refs);

    $commit_map = array();
    foreach ($refs as $key => $ref) {
      $is_commit = $ref->hasType('commit');

      $token = $ref->getToken();

      if ($token === '.') {
        // Git resolves "." like HEAD, but we want to treat it as "browse the
        // current directory" instead in all cases.
        continue;
      }

      if ($token === null) {
        if ($is_commit) {
          $token = $api->getHeadCommit();
        } else {
          continue;
        }
      }

      try {
        $commit = $api->getCanonicalRevisionName($token);
        if ($commit) {
          $commit_map[$commit][] = $key;
        }
      } catch (Exception $ex) {
        // Ignore anything we can't resolve.
      }
    }

    if (!$commit_map) {
      return array();
    }

    $commit_info = $this->resolveCall(
      'diffusion.querycommits',
      array(
        'repositoryPHID' => $repository_phid,
        'names' => array_keys($commit_map),
      ));

    $results = array();
    foreach ($commit_info['identifierMap'] as $commit_key => $commit_phid) {
      foreach ($commit_map[$commit_key] as $key) {
        $commit_uri = $commit_info['data'][$commit_phid]['uri'];

        $results[$key][] = id(new ArcanistBrowseURIRef())
          ->setURI($commit_uri)
          ->setType('commit');
      }
    }

    return $results;
  }


}
