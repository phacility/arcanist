<?php

final class ArcanistBrowseRef
  extends ArcanistRef {

  private $token;
  private $types;
  private $branch;

  public function getRefIdentifier() {
    return pht('Browse Query "%s"', $this->getToken());
  }

  public function defineHardpoints() {
    return array(
      'uris' => array(
        'type' => 'ArcanistBrowseURIRef',
        'vector' => true,
      ),
    );
  }

  public function setToken($token) {
    $this->token = $token;
    return $this;
  }

  public function getToken() {
    return $this->token;
  }

  public function setTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function getTypes() {
    return $this->types;
  }

  public function hasType($type) {
    $map = $this->getTypes();
    $map = array_fuse($map);
    return isset($map[$type]);
  }

  public function isUntyped() {
    return !$this->types;
  }

  public function setBranch($branch) {
    $this->branch = $branch;
    return $this;
  }

  public function getBranch() {
    return $this->branch;
  }

  public function getURIs() {
    return $this->getHardpoint('uris');
  }

}
