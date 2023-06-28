<?php

final class ArcanistFileRef
  extends ArcanistRef {

  private $parameters;

  public function getRefDisplayName() {
    return pht('File "%s"', $this->getMonogram());
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

  public function getDataURI() {
    return idxv($this->parameters, array('fields', 'dataURI'));
  }

  public function getSize() {
    return idxv($this->parameters, array('fields', 'size'));
  }

  public function getURI() {
    $uri = idxv($this->parameters, array('fields', 'uri'));

    if ($uri === null) {
      $uri = '/'.$this->getMonogram();
    }

    return $uri;
  }

  public function getMonogram() {
    return 'F'.$this->getID();
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getMonogram())
      ->setTitle($this->getName());
  }

}
