<?php

abstract class ArcanistHardpointObject
  extends Phobject {

  private $hardpointList;

  public function __clone() {
    if ($this->hardpointList) {
      $this->hardpointList = clone $this->hardpointList;
    }
  }

  final public function getHardpoint($hardpoint) {
    return $this->getHardpointList()->getHardpoint(
      $this,
      $hardpoint);
  }

  final public function attachHardpoint($hardpoint, $value) {
    $this->getHardpointList()->attachHardpoint(
      $this,
      $hardpoint,
      $value);

    return $this;
  }

  final public function mergeHardpoint($hardpoint, $value) {
    $hardpoint_list = $this->getHardpointList();
    $hardpoint_def = $hardpoint_list->getHardpointDefinition(
      $this,
      $hardpoint);

    $old_value = $this->getHardpoint($hardpoint);
    $new_value = $hardpoint_def->mergeHardpointValues(
      $this,
      $old_value,
      $value);

    $hardpoint_list->setHardpointValue(
      $this,
      $hardpoint,
      $new_value);

    return $this;
  }

  final public function hasHardpoint($hardpoint) {
    return $this->getHardpointList()->hasHardpoint($this, $hardpoint);
  }

  final public function hasAttachedHardpoint($hardpoint) {
    return $this->getHardpointList()->hasAttachedHardpoint(
      $this,
      $hardpoint);
  }

  protected function newHardpoints() {
    return array();
  }

  final protected function newHardpoint($hardpoint_key) {
    return id(new ArcanistScalarHardpoint())
      ->setHardpointKey($hardpoint_key);
  }

  final protected function newVectorHardpoint($hardpoint_key) {
    return id(new ArcanistVectorHardpoint())
      ->setHardpointKey($hardpoint_key);
  }

  final protected function newTemplateHardpoint(
    $hardpoint_key,
    ArcanistHardpoint $template) {

    return id(clone $template)
      ->setHardpointKey($hardpoint_key);
  }


  final public function getHardpointList() {
    if ($this->hardpointList === null) {
      $list = $this->newHardpointList();

      // TODO: Cache the hardpoint list with the class name as a key? If so,
      // it needs to be purged when the request cache is purged.

      $hardpoints = $this->newHardpoints();

      // TODO: Verify the hardpoints list is structured properly.

      $list->setHardpoints($hardpoints);

      $this->hardpointList = $list;
    }

    return $this->hardpointList;
  }

  private function newHardpointList() {
    return new ArcanistHardpointList();
  }

}
