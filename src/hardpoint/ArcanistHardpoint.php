<?php

abstract class ArcanistHardpoint
  extends Phobject {

  private $hardpointKey;

  public function setHardpointKey($hardpoint_key) {
    $this->hardpointKey = $hardpoint_key;
    return $this;
  }

  public function getHardpointKey() {
    return $this->hardpointKey;
  }

  abstract public function isVectorHardpoint();

  public function mergeHardpointValues(
    ArcanistHardpointObject $object,
    $old,
    $new) {

    throw new Exception(
      pht(
        'Hardpoint ("%s", of type "%s") does not support merging '.
        'values.',
        $this->getHardpointKey(),
        get_class($this)));
  }

}
