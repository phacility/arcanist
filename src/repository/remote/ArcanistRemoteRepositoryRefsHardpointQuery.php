<?php

final class ArcanistRemoteRepositoryRefsHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRemoteRef::HARDPOINT_REPOSITORYREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRemoteRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $api = $this->getRepositoryAPI();

    $uris = array();
    foreach ($refs as $remote) {
      $fetch_uri = $remote->getFetchURI();
      if ($fetch_uri !== null) {
        $uris[] = $fetch_uri;
      }

      $push_uri = $remote->getPushURI();
      if ($push_uri !== null) {
        $uris[] = $push_uri;
      }
    }

    if (!$uris) {
      yield $this->yieldValue($refs, array());
    }

    $uris = array_fuse($uris);
    $uris = array_values($uris);

    $search_future = $this->newConduitSearch(
      'diffusion.repository.search',
      array(
        'uris' => $uris,
      ),
      array(
        'uris' => true,
      ));

    $repository_info = (yield $this->yieldFuture($search_future));

    $repository_refs = array();
    foreach ($repository_info as $raw_result) {
      $repository_refs[] = ArcanistRepositoryRef::newFromConduit($raw_result);
    }

    $uri_map = array();
    foreach ($repository_refs as $repository_ref) {
      foreach ($repository_ref->getURIs() as $repository_uri) {
        $repository_uri = $api->getNormalizedURI($repository_uri);
        $uri_map[$repository_uri] = $repository_ref;
      }
    }

    $results = array();
    foreach ($refs as $key => $remote) {
      $result = array();

      $fetch_uri = $remote->getFetchURI();
      if ($fetch_uri !== null) {
        $fetch_uri = $api->getNormalizedURI($fetch_uri);
        if (isset($uri_map[$fetch_uri])) {
          $result[] = $uri_map[$fetch_uri];
        }
      }

      $push_uri = $remote->getPushURI();
      if ($push_uri !== null) {
        $push_uri = $api->getNormalizedURI($push_uri);
        if (isset($uri_map[$push_uri])) {
          $result[] = $uri_map[$push_uri];
        }
      }

      $results[$key] = $result;
    }

    yield $this->yieldMap($results);
  }

}
