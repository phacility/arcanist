<?php

final class ArcanistTaskRef
  extends ArcanistRef {

  private $parameters;

  public function getRefDisplayName() {
    return pht('Task "%s"', $this->getMonogram());
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

  public function getMonogram() {
    return 'T'.$this->getID();
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getMonogram())
      ->setTitle($this->getName());
  }

}
