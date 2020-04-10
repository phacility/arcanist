<?php

abstract class ArcanistBrowseURIHardpointQuery
  extends ArcanistWorkflowHardpointQuery {

  public function getSupportedBrowseType() {
    return $this->getPhobjectClassConstant('BROWSETYPE', 32);
  }

  public function getHardpoints() {
    return array(
      ArcanistBrowseRefPro::HARDPOINT_URIS,
    );
  }

  protected function canLoadRef(ArcanistRefPro $ref) {
    return ($ref instanceof ArcanistBrowseRefPro);
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
    return id(new ArcanistBrowseURIRefPro())
      ->setType($this->getSupportedBrowseType());
  }

}
