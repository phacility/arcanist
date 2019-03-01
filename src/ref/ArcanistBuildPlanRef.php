<?php

final class ArcanistBuildPlanRef
  extends Phobject {

  private $parameters;

  public static function newFromConduit(array $data) {
    $ref = new self();
    $ref->parameters = $data;
    return $ref;
  }

  public function getPHID() {
    return $this->parameters['phid'];
  }

  public function getBehavior($behavior_key, $default = null) {
    return idxv(
      $this->parameters,
      array('fields', 'behaviors', $behavior_key, 'value'),
      $default);
  }

}
