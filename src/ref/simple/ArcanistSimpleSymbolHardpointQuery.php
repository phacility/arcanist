<?php

final class ArcanistSimpleSymbolHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistFileSymbolRef::HARDPOINT_OBJECT,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistSimpleSymbolRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $id_map = array();
    $phid_map = array();

    foreach ($refs as $key => $ref) {
      switch ($ref->getSymbolType()) {
        case ArcanistSimpleSymbolRef::TYPE_ID:
          $id_map[$key] = $ref->getSymbol();
          break;
        case ArcanistSimpleSymbolRef::TYPE_PHID:
          $phid_map[$key] = $ref->getSymbol();
          break;
      }
    }

    $template_ref = head($refs);

    $conduit_method =
      $template_ref->getSimpleSymbolConduitSearchMethodName();
    $conduit_attachments =
      $template_ref->getSimpleSymbolConduitSearchAttachments();

    $futures = array();

    if ($id_map) {
      $id_future = $this->newConduitSearch(
        $conduit_method,
        array(
          'ids' => array_values(array_fuse($id_map)),
        ),
        $conduit_attachments);

      $futures[] = $id_future;
    } else {
      $id_future = null;
    }

    if ($phid_map) {
      $phid_future = $this->newConduitSearch(
        $ref->getSimpleSymbolConduitSearchMethodName(),
        array(
         'phids' => array_values(array_fuse($phid_map)),
        ),
        $conduit_attachments);

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

    $object_ref = $template_ref->newSimpleSymbolObjectRef();

    foreach ($result_map as $key => $raw_result) {
      if ($raw_result === null) {
        continue;
      }

      $result_map[$key] = call_user_func_array(
        array(get_class($object_ref), 'newFromConduit'),
        array($raw_result));
    }

    yield $this->yieldMap($result_map);
  }

}
