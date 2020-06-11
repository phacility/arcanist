<?php

final class ArcanistBuildRef
  extends ArcanistRef {

  const HARDPOINT_BUILDPLANREF = 'ref.build.buildplanRef';

  private $parameters;

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_BUILDPLANREF),
    );
  }

  public function getRefDisplayName() {
    return pht('Build %d', $this->getID());
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

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getRefDisplayName())
      ->setTitle($this->getName());
  }

  public function getBuildPlanRef() {
    return $this->getHardpoint(self::HARDPOINT_BUILDPLANREF);
  }

  public function getBuildablePHID() {
    return idxv($this->parameters, array('fields', 'buildablePHID'));
  }

  public function getBuildPlanPHID() {
    return idxv($this->parameters, array('fields', 'buildPlanPHID'));
  }

  public function getStatus() {
    return idxv($this->parameters, array('fields', 'buildStatus', 'value'));
  }

  public function getStatusName() {
    return idxv($this->parameters, array('fields', 'buildStatus', 'name'));
  }

  public function getStatusANSIColor() {
    return idxv(
      $this->parameters,
      array('fields', 'buildStatus', 'color.ansi'));
  }

  public function isComplete() {
    switch ($this->getStatus()) {
      case 'passed':
      case 'failed':
      case 'aborted':
      case 'error':
      case 'deadlocked':
        return true;
      default:
        return false;
    }
  }

  public function isPassed() {
    return ($this->getStatus() === 'passed');
  }

  public function getStatusSortVector() {
    $status = $this->getStatus();

    // For now, just sort passed builds first.
    if ($this->isPassed()) {
      $status_class = 1;
    } else {
      $status_class = 2;
    }

    return id(new PhutilSortVector())
      ->addInt($status_class)
      ->addString($status);
  }

}
