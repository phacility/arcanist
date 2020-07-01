<?php

final class ArcanistPasteRef
  extends ArcanistRef {

  private $parameters;

  public function getRefDisplayName() {
    return pht('Paste "%s"', $this->getMonogram());
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

  public function getTitle() {
    return idxv($this->parameters, array('fields', 'title'));
  }

  public function getURI() {
    $uri = idxv($this->parameters, array('fields', 'uri'));

    if ($uri === null) {
      $uri = '/'.$this->getMonogram();
    }

    return $uri;
  }

  public function getContent() {
    return idxv($this->parameters, array('attachments', 'content', 'content'));
  }

  public function getMonogram() {
    return 'P'.$this->getID();
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view
      ->setObjectName($this->getMonogram())
      ->setTitle($this->getTitle());
  }

}
