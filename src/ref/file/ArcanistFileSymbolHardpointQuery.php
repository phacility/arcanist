<?php

final class ArcanistFileSymbolHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistFileSymbolRef::HARDPOINT_OBJECT,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistFileSymbolRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $id_map = array();
    $phid_map = array();

    foreach ($refs as $key => $ref) {
      switch ($ref->getSymbolType()) {
        case ArcanistFileSymbolRef::TYPE_ID:
          $id_map[$key] = $ref->getSymbol();
          break;
        case ArcanistFileSymbolRef::TYPE_PHID:
          $phid_map[$key] = $ref->getSymbol();
          break;
      }
    }

    $futures = array();

    if ($id_map) {
      $id_future = $this->newConduitSearch(
        'file.search',
        array(
          'ids' => array_values(array_fuse($id_map)),
        ));

      $futures[] = $id_future;
    } else {
      $id_future = null;
    }

    if ($phid_map) {
      $phid_future = $this->newConduitSearch(
        'file.search',
        array(
         'phids' => array_values(array_fuse($phid_map)),
        ));

      $futures[] = $phid_future;
    } else {
      $phid_future = null;
    }

    yield $this->yieldFutures($futures);

    $result_map = array();

    if ($id_future) {
      $id_results = $id_future->resolve();
      $id_results = ipull($id_results, null, 'id');

      foreach ($id_map as $key => $id) {
        $result_map[$key] = idx($id_results, $id);
      }
    }

    if ($phid_future) {
      $phid_results = $phid_future->resolve();
      $phid_results = ipull($phid_results, null, 'phid');

      foreach ($phid_map as $key => $phid) {
        $result_map[$key] = idx($phid_results, $phid);
      }
    }

    foreach ($result_map as $key => $raw_result) {
      if ($raw_result === null) {
        continue;
      }

      $result_map[$key] = ArcanistFileRef::newFromConduit($raw_result);
    }

    yield $this->yieldMap($result_map);
  }

}
