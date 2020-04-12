<?php

final class ArcanistRevisionSymbolHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRevisionSymbolRef::HARDPOINT_OBJECT,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRevisionSymbolRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $id_map = mpull($refs, 'getSymbol');
    $id_set = array_fuse($id_map);

    $revisions = (yield $this->yieldConduit(
      'differential.query',
      array(
        'ids' => $id_set,
      )));

    $refs = array();
    foreach ($revisions as $revision) {
      $ref = ArcanistRevisionRef::newFromConduit($revision);
      $refs[$ref->getID()] = $ref;
    }

    $results = array();
    foreach ($id_map as $key => $id) {
      $results[$key] = idx($refs, $id);
    }

    yield $this->yieldMap($results);
  }

}
