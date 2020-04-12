<?php

abstract class ArcanistBrowseURIHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  public function getSupportedBrowseType() {
    return $this->getPhobjectClassConstant('BROWSETYPE', 32);
  }

  public function getHardpoints() {
    return array(
      ArcanistBrowseRef::HARDPOINT_URIS,
    );
  }

  protected function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBrowseRef);
  }

  public function getRefsWithSupportedTypes(array $refs) {
    $type = $this->getSupportedBrowseType();

    foreach ($refs as $key => $ref) {
      if ($ref->isUntyped()) {
        continue;
      }

      if ($ref->hasType($type)) {
        continue;
      }

      unset($refs[$key]);
    }

    return $refs;
  }

  public static function getAllBrowseQueries() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();
  }

  final protected function newBrowseURIRef() {
    return id(new ArcanistBrowseURIRef())
      ->setType($this->getSupportedBrowseType());
  }

}
