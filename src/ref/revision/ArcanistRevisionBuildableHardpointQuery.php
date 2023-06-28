<?php

final class ArcanistRevisionBuildableHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistRevisionRef::HARDPOINT_BUILDABLEREF,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistRevisionRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $diff_map = array();
    foreach ($refs as $key => $revision_ref) {
      $diff_phid = $revision_ref->getDiffPHID();
      if ($diff_phid) {
        $diff_map[$key] = $diff_phid;
      }
    }

    if (!$diff_map) {
      yield $this->yieldValue($refs, null);
    }

    $buildables = (yield $this->yieldConduitSearch(
      'harbormaster.buildable.search',
      array(
        'objectPHIDs' => array_values($diff_map),
        'manual' => false,
      )));

    $buildable_refs = array();
    foreach ($buildables as $buildable) {
      $buildable_ref = ArcanistBuildableRef::newFromConduit($buildable);
      $object_phid = $buildable_ref->getObjectPHID();
      $buildable_refs[$object_phid] = $buildable_ref;
    }

    $results = array_fill_keys(array_keys($refs), null);
    foreach ($refs as $key => $revision_ref) {
      if (!isset($diff_map[$key])) {
        continue;
      }

      $diff_phid = $diff_map[$key];
      if (!isset($buildable_refs[$diff_phid])) {
        continue;
      }

      $results[$key] = $buildable_refs[$diff_phid];
    }

    yield $this->yieldMap($results);
  }

}
