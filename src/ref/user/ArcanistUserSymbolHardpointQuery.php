<?php

final class ArcanistUserSymbolHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistUserSymbolRef::HARDPOINT_OBJECT,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistUserSymbolRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $id_map = array();
    $phid_map = array();
    $username_map = array();
    $function_map = array();

    foreach ($refs as $key => $ref) {
      switch ($ref->getSymbolType()) {
        case ArcanistUserSymbolRef::TYPE_ID:
          $id_map[$key] = $ref->getSymbol();
          break;
        case ArcanistUserSymbolRef::TYPE_PHID:
          $phid_map[$key] = $ref->getSymbol();
          break;
        case ArcanistUserSymbolRef::TYPE_USERNAME:
          $username_map[$key] = $ref->getSymbol();
          break;
        case ArcanistUserSymbolRef::TYPE_FUNCTION:
          $symbol = $ref->getSymbol();
          if ($symbol !== 'viewer()') {
            throw new Exception(
              pht(
                'Only the function "viewer()" is supported.'));
          }
          $function_map[$key] = $symbol;
          break;
      }
    }

    $futures = array();

    if ($function_map) {
      // The only function we support is "viewer()".
      $function_future = $this->newConduit(
        'user.whoami',
        array());

      $futures[] = $function_future;
    } else {
      $function_future = null;
    }

    if ($id_map) {
      $id_future = $this->newConduitSearch(
        'user.search',
        array(
          'ids' => array_values(array_fuse($id_map)),
        ));

      $futures[] = $id_future;
    } else {
      $id_future = null;
    }

    if ($phid_map) {
      $phid_future = $this->newConduitSearch(
        'user.search',
        array(
         'phids' => array_values(array_fuse($phid_map)),
        ));

      $futures[] = $phid_future;
    } else {
      $phid_future = null;
    }

    if ($username_map) {
      $username_future = $this->newConduitSearch(
        'user.search',
        array(
          'usernames' => array_values(array_fuse($username_map)),
        ));

      $futures[] = $username_future;
    } else {
      $username_future = null;
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

    if ($username_future) {
      $raw_results = $username_future->resolve();

      $username_results = array();
      foreach ($raw_results as $raw_result) {
        $username = idxv($raw_result, array('fields', 'username'));
        $username_results[$username] = $raw_result;
      }

      foreach ($username_map as $key => $username) {
        $result_map[$key] = idx($username_results, $username);
      }
    }

    foreach ($result_map as $key => $raw_result) {
      if ($raw_result === null) {
        continue;
      }

      $result_map[$key] = ArcanistUserRef::newFromConduit($raw_result);
    }

    if ($function_future) {
      $raw_result = $function_future->resolve();

      if ($raw_result === null) {
        $function_ref = null;
      } else {
        $function_ref = ArcanistUserRef::newFromConduitWhoami($raw_result);
      }

      foreach ($function_map as $key => $function) {
        $result_map[$key] = $function_ref;
      }
    }

    yield $this->yieldMap($result_map);
  }

}
