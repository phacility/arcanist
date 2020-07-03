<?php

final class ArcanistBuildableBuildsHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistBuildableRef::HARDPOINT_BUILDREFS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBuildableRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $buildable_phids = mpull($refs, 'getPHID');

    $builds = (yield $this->yieldConduitSearch(
      'harbormaster.build.search',
      array(
        'buildables' => $buildable_phids,
      )));

    $build_refs = array();
    foreach ($builds as $build) {
      $build_ref = ArcanistBuildRef::newFromConduit($build);
      $build_refs[] = $build_ref;
    }

    $build_refs = mgroup($build_refs, 'getBuildablePHID');

    $results = array();
    foreach ($refs as $key => $buildable_ref) {
      $buildable_phid = $buildable_ref->getPHID();
      $buildable_builds = idx($build_refs, $buildable_phid, array());
      $results[$key] = $buildable_builds;
    }

    yield $this->yieldMap($results);
  }

}
