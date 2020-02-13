<?php

abstract class ArcanistBrowseURIHardpointLoader
  extends ArcanistHardpointLoader {

  public function getSupportedBrowseType() {
    return $this->getPhobjectClassConstant('BROWSETYPE', 32);
  }

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return true;
  }

  public function canLoadRef(ArcanistRef $ref) {
    return ($ref instanceof ArcanistBrowseRef);
  }

  public function canLoadHardpoint(ArcanistRef $ref, $hardpoint) {
    return ($hardpoint == 'uris');
  }

  public function willLoadBrowseURIRefs(array $refs) {
    return;
  }

  public function didFailToLoadBrowseURIRefs(array $refs) {
    return;
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

  public static function getAllBrowseLoaders() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getLoaderKey')
      ->execute();
  }

}
