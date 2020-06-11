<?php

final class ArcanistBuildPlanRef
  extends ArcanistRef {

  private $parameters;

  public function getRefDisplayName() {
    return pht('Build Plan %d', $this->getID());
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

  public function getBehavior($behavior_key, $default = null) {
    return idxv(
      $this->parameters,
      array('fields', 'behaviors', $behavior_key, 'value'),
      $default);
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getRefDisplayName())
      ->setTitle($this->getName());
  }

}
