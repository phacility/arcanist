<?php

abstract class ArcanistRef
  extends Phobject {

  private $hardpoints = array();

  abstract public function getRefIdentifier();
  abstract public function defineHardpoints();

  final public function hasHardpoint($hardpoint) {
    $map = $this->getHardpointMap();
    return isset($map[$hardpoint]);
  }

  final public function hasAttachedHardpoint($hardpoint) {
    if (array_key_exists($hardpoint, $this->hardpoints)) {
      return true;
    }

    return $this->canReadHardpoint($hardpoint);
  }

  final public function attachHardpoint($hardpoint, $value) {
    if (!$this->hasHardpoint($hardpoint)) {
      throw new Exception(pht('No hardpoint "%s".', $hardpoint));
    }

    $this->hardpoints[$hardpoint] = $value;

    return $this;
  }

  final public function appendHardpoint($hardpoint, array $value) {
    if (!$this->isVectorHardpoint($hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint "%s" is not a vector hardpoint.',
          $hardpoint));
    }

    if (!isset($this->hardpoints[$hardpoint])) {
      $this->hardpoints[$hardpoint] = array();
    }

    $this->hardpoints[$hardpoint] = $this->mergeHardpoint(
      $hardpoint,
      $this->hardpoints[$hardpoint],
      $value);

    return $this;
  }

  protected function mergeHardpoint($hardpoint, array $src, array $new) {
    foreach ($new as $value) {
      $src[] = $value;
    }
    return $src;
  }

  final public function isVectorHardpoint($hardpoint) {
    if (!$this->hasHardpoint($hardpoint)) {
      return false;
    }

    $map = $this->getHardpointMap();
    $spec = idx($map, $hardpoint, array());

    return (idx($spec, 'vector') === true);
  }

  final public function getHardpoint($hardpoint) {
    if (!$this->hasAttachedHardpoint($hardpoint)) {
      if (!$this->hasHardpoint($hardpoint)) {
        throw new Exception(
          pht(
            'Ref does not have hardpoint "%s"!',
            $hardpoint));
      } else {
        throw new Exception(
          pht(
            'Hardpoint "%s" is not attached!',
            $hardpoint));
      }
    }

    if (array_key_exists($hardpoint, $this->hardpoints)) {
      return $this->hardpoints[$hardpoint];
    }

    return $this->readHardpoint($hardpoint);
  }

  private function getHardpointMap() {
    return $this->defineHardpoints();
  }

  protected function canReadHardpoint($hardpoint) {
    return false;
  }

  protected function readHardpoint($hardpoint) {
    throw new Exception(pht('Can not read hardpoint "%s".', $hardpoint));
  }

}
