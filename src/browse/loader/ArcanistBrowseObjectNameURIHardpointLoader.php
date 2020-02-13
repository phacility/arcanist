<?php

final class ArcanistBrowseObjectNameURIHardpointLoader
  extends ArcanistBrowseURIHardpointLoader {

  const LOADERKEY = 'browse.uri.name';
  const BROWSETYPE = 'object';

  public function loadHardpoints(array $refs, $hardpoint) {
    $refs = $this->getRefsWithSupportedTypes($refs);

    $name_map = array();
    foreach ($refs as $key => $ref) {
      $token = $ref->getToken();
      if (!strlen($token)) {
        continue;
      }

      $name_map[$key] = $token;
    }

    if (!$name_map) {
      return array();
    }

    $objects = $this->resolveCall(
      'phid.lookup',
      array(
        'names' => $name_map,
      ));

    $result = array();

    $reverse_map = array_flip($name_map);
    foreach ($objects as $name => $object) {
      $key = idx($reverse_map, $name);
      if ($key === null) {
        continue;
      }

      $uri = idx($object, 'uri');
      if (!strlen($uri)) {
        continue;
      }

      $result[$key][] = id(new ArcanistBrowseURIRef())
        ->setURI($object['uri'])
        ->setType('object');
    }

    return $result;
  }

}
