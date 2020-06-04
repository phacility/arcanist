<?php

final class ArcanistRevisionAuthorHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRevisionRef::HARDPOINT_AUTHORREF,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRevisionRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {

    $symbols = array();
    foreach ($refs as $key => $ref) {
      $symbols[$key] = id(new ArcanistUserSymbolRef())
        ->setSymbol($ref->getAuthorPHID());
    }

    yield $this->yieldRequests(
      $symbols,
      array(
        ArcanistSymbolRef::HARDPOINT_OBJECT,
      ));

    $results = mpull($symbols, 'getObject');

    yield $this->yieldMap($results);
  }

}
