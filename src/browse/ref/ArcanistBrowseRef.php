<?php

final class ArcanistBrowseRef
  extends ArcanistRef {

  const HARDPOINT_URIS = 'uris';
  const HARDPOINT_COMMITREFS = 'commitRefs';

  private $token;
  private $types = array();
  private $branch;

  public function getRefDisplayName() {
    return pht('Browse Query "%s"', $this->getToken());
  }

  protected function newHardpoints() {
    return array(
      $this->newVectorHardpoint(self::HARDPOINT_COMMITREFS),
      $this->newVectorHardpoint(self::HARDPOINT_URIS),
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
    return $this->getHardpoint(self::HARDPOINT_URIS);
  }

  public function getCommitRefs() {
    return $this->getHardpoint(self::HARDPOINT_COMMITREFS);
  }

}
