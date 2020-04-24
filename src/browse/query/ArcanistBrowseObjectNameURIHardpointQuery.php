<?php

final class ArcanistBrowseObjectNameURIHardpointQuery
  extends ArcanistBrowseURIHardpointQuery {

  const BROWSETYPE = 'object';

  public function loadHardpoint(array $refs, $hardpoint) {
    $refs = $this->getRefsWithSupportedTypes($refs);
    if (!$refs) {
      yield $this->yieldMap(array());
    }

    $name_map = array();
    $token_set = array();
    foreach ($refs as $key => $ref) {
      $token = $ref->getToken();
      if (!strlen($token)) {
        continue;
      }

      $name_map[$key] = $token;
      $token_set[$token] = $token;
    }

    if (!$token_set) {
      yield $this->yieldMap(array());
    }

    $objects = (yield $this->yieldConduit(
      'phid.lookup',
      array(
        'names' => $token_set,
      )));

    $result = array();
    foreach ($name_map as $ref_key => $token) {
      $object = idx($objects, $token);

      if (!$object) {
        continue;
      }

      $uri = idx($object, 'uri');
      if (!strlen($uri)) {
        continue;
      }

      $result[$ref_key][] = $this->newBrowseURIRef()
        ->setURI($object['uri']);
    }

    yield $this->yieldMap($result);
  }

}
