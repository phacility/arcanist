<?php

final class ArcanistRevisionParentRevisionsHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRevisionRef::HARDPOINT_PARENTREVISIONREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRevisionRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $parameters = array(
      'sourcePHIDs' => mpull($refs, 'getPHID'),
      'types' => array(
        'revision.parent',
      ),
    );

    $data = array();
    while (true) {
      $results = (yield $this->yieldConduit(
        'edge.search',
        $parameters));

      foreach ($results['data'] as $item) {
        $data[] = $item;
      }

      if ($results['cursor']['after'] === null) {
        break;
      }

      $parameters['after'] = $results['cursor']['after'];
    }

    if (!$data) {
      yield $this->yieldValue($refs, array());
    }

    $map = array();
    $symbols = array();
    foreach ($data as $edge) {
      $src = $edge['sourcePHID'];
      $dst = $edge['destinationPHID'];

      $map[$src][$dst] = $dst;

      $symbols[$dst] = id(new ArcanistRevisionSymbolRef())
        ->setSymbol($dst);
    }

    yield $this->yieldRequests(
      $symbols,
      array(
        ArcanistSymbolRef::HARDPOINT_OBJECT,
      ));

    $objects = array();
    foreach ($symbols as $key => $symbol) {
      $object = $symbol->getObject();
      if ($object) {
        $objects[$key] = $object;
      }
    }

    $results = array_fill_keys(array_keys($refs), array());
    foreach ($refs as $ref_key => $ref) {
      $revision_phid = $ref->getPHID();
      $parent_phids = idx($map, $revision_phid, array());
      $parent_refs = array_select_keys($objects, $parent_phids);
      $results[$ref_key] = $parent_refs;
    }

    yield $this->yieldMap($results);
  }

}
