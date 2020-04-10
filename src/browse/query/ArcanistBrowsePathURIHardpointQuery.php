<?php

final class ArcanistBrowsePathURIHardpointQuery
  extends ArcanistBrowseURIHardpointQuery {

  const BROWSETYPE = 'path';

  public function willLoadBrowseURIRefs(array $refs) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      return;
    }

    $query = $this->getQuery();

    $working_ref = $query->getWorkingCopyRef();
    if (!$working_ref) {
      echo pht(
        'NO WORKING COPY: The current directory is not a repository '.
        'working copy, so arguments can not be resolved as paths. Run '.
        'this command inside a working copy to resolve paths.');
      echo "\n";
      return;
    }

    $repository_ref = $query->getRepositoryRef();
    if (!$repository_ref) {
      echo pht(
        'NO REPOSITORY: Unable to determine which repository this working '.
        'copy belongs to, so arguments can not be resolved as paths. Use '.
        '"%s" to understand how repositories are resolved.',
        'arc which');
      echo "\n";
      return;
    }
  }

  public function didFailToLoadBrowseURIRefs(array $refs) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      return;
    }

    $query = $this->getQuery();

    $working_ref = $query->getWorkingCopyRef();
    if (!$working_ref) {
      return;
    }

    $repository_ref = $query->getRepositoryRef();
    if (!$repository_ref) {
      return;
    }

    echo pht(
      'Use "--types path" to force arguments to be interpreted as paths.');
    echo "\n";
  }


  public function loadHardpoint(array $refs, $hardpoint) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      yield $this->yieldMap(array());
    }

    $repository_ref = (yield $this->yieldRepositoryRef());
    if (!$repository_ref) {
      yield $this->yieldMap(array());
    }

    $working_copy = $this->getWorkingCopy();
    $working_root = $working_copy->getPath();

    $results = array();
    foreach ($refs as $key => $ref) {
      $is_path = $ref->hasType(self::BROWSETYPE);

      $path = $ref->getToken();
      if ($path === null) {
        // If we're explicitly resolving no arguments as a path, treat it
        // as the current working directory.
        if ($is_path) {
          $path = '.';
        } else {
          continue;
        }
      }

      $lines = null;
      $parts = explode(':', $path);
      if (count($parts) > 1) {
        $lines = array_pop($parts);
      }
      $path = implode(':', $parts);

      $full_path = Filesystem::resolvePath($path);

      if (!Filesystem::pathExists($full_path)) {
        if (!$is_path) {
          continue;
        }
      }

      if ($full_path == $working_root) {
        $path = '';
      } else {
        $path = Filesystem::readablePath($full_path, $working_root);
      }

      $params = array(
        'path' => $path,
        'lines' => $lines,
        'branch' => $ref->getBranch(),
      );

      $uri = $repository_ref->newBrowseURI($params);

      $results[$key][] = $this->newBrowseURIRef()
        ->setURI($uri);
    }

    yield $this->yieldMap($results);
  }


}
