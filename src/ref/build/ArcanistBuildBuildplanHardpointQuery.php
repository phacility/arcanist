<?php

final class ArcanistBuildBuildplanHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getHardpoints() {
    return array(
      ArcanistBuildRef::HARDPOINT_BUILDPLANREF,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBuildRef);
  }

  public function loadHardpoint(array $refs, $hardpoint) {
    $plan_phids = mpull($refs, 'getBuildPlanPHID');
    $plan_phids = array_fuse($plan_phids);
    $plan_phids = array_values($plan_phids);

    $plans = (yield $this->yieldConduitSearch(
      'harbormaster.buildplan.search',
      array(
        'phids' => $plan_phids,
      )));

    $plan_refs = array();
    foreach ($plans as $plan) {
      $plan_ref = ArcanistBuildPlanRef::newFromConduit($plan);
      $plan_refs[] = $plan_ref;
    }
    $plan_refs = mpull($plan_refs, null, 'getPHID');

    $results = array();
    foreach ($refs as $key => $build_ref) {
      $plan_phid = $build_ref->getBuildPlanPHID();
      $plan = idx($plan_refs, $plan_phid);
      $results[$key] = $plan;
    }

    yield $this->yieldMap($results);
  }

}
