<?php

final class ArcanistBuildPlanRef
  extends ArcanistRef
  implements
    ArcanistDisplayRefInterface {

  private $parameters;

  public function getRefDisplayName() {
    return $this->getDisplayRefObjectName();
  }

  public static function newFromConduit(array $parameters) {
    $ref = new self();
    $ref->parameters = $parameters;
    return $ref;
  }

  public function getID() {
    return idx($this->parameters, 'id');
  }

  public function getPHID() {
    return idx($this->parameters, 'phid');
  }

  public function getName() {
    return idxv($this->parameters, array('fields', 'name'));
  }

  public function getDisplayRefObjectName() {
    return pht('Build Plan %d', $this->getID());
  }

  public function getDisplayRefTitle() {
    return $this->getName();
  }

  public function getBehavior($behavior_key, $default = null) {
    return idxv(
      $this->parameters,
      array('fields', 'behaviors', $behavior_key, 'value'),
      $default);
  }

}
