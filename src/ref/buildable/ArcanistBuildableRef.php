<?php

final class ArcanistBuildableRef
  extends ArcanistRef {

  const HARDPOINT_BUILDREFS = 'ref.buildable.buildRefs';

  private $parameters;

  protected function newHardpoints() {
    $object_list = new ArcanistObjectListHardpoint();
    return array(
      $this->newTemplateHardpoint(
        self::HARDPOINT_BUILDREFS,
        $object_list),
    );
  }

  public function getRefDisplayName() {
    return pht('Buildable "%s"', $this->getMonogram());
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

  public function getObjectPHID() {
    return idxv($this->parameters, array('fields', 'objectPHID'));
  }

  public function getMonogram() {
    return 'B'.$this->getID();
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getMonogram())
      ->setTitle($this->getRefDisplayName());
  }

  public function getBuildRefs() {
    return $this->getHardpoint(self::HARDPOINT_BUILDREFS);
  }

  public function getURI() {
    $uri = idxv($this->parameters, array('fields', 'uri'));

    if ($uri === null) {
      $uri = '/'.$this->getMonogram();
    }

    return $uri;
  }

}
