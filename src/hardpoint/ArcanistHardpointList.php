<?php

final class ArcanistHardpointList
  extends Phobject {

  private $hardpoints = array();
  private $attached = array();
  private $data = array();

  public function setHardpoints(array $hardpoints) {
    assert_instances_of($hardpoints, 'ArcanistHardpoint');

    $map = array();
    foreach ($hardpoints as $idx => $hardpoint) {
      $key = $hardpoint->getHardpointKey();

      if (!strlen($key)) {
        throw new Exception(
          pht(
            'Hardpoint (at index "%s") has no hardpoint key. Each hardpoint '.
            'must have a key that is unique among hardpoints on the object.',
            $idx));
      }

      if (isset($map[$key])) {
        throw new Exception(
          pht(
            'Hardpoint (at index "%s") has the same key ("%s") as an earlier '.
            'hardpoint. Each hardpoint must have a key that is unique '.
            'among hardpoints on the object.',
            $idx,
            $key));
      }

      $map[$key] = $hardpoint;
    }

    $this->hardpoints = $map;

    return $this;
  }

  public function hasHardpoint($object, $hardpoint) {
    return isset($this->hardpoints[$hardpoint]);
  }

  public function hasAttachedHardpoint($object, $hardpoint) {
    return isset($this->attached[$hardpoint]);
  }

  public function getHardpoints() {
    return $this->hardpoints;
  }

  public function getHardpointDefinition($object, $hardpoint) {
    if (!$this->hasHardpoint($object, $hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint ("%s") is not registered on this object (of type "%s") '.
          'so the definition object does not exist. Hardpoints are: %s.',
          $hardpoint,
          phutil_describe_type($object),
          $this->getHardpointListForDisplay()));
    }

    return $this->hardpoints[$hardpoint];
  }

  public function getHardpoint($object, $hardpoint) {
    if (!$this->hasHardpoint($object, $hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint ("%s") is not registered on this object (of type "%s"). '.
          'Hardpoints are: %s.',
          $hardpoint,
          phutil_describe_type($object),
          $this->getHardpointListForDisplay()));
    }

    if (!$this->hasAttachedHardpoint($object, $hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint data (for hardpoint "%s") is not attached.',
          $hardpoint));
    }

    return $this->data[$hardpoint];
  }

  public function setHardpointValue($object, $hardpoint, $value) {
    if (!$this->hasHardpoint($object, $hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint ("%s") is not registered on this object (of type "%s"). '.
          'Hardpoints are: %s.',
          $hardpoint,
          phutil_describe_type($object),
          $this->getHardpointListforDisplay()));
    }

    $this->attached[$hardpoint] = true;
    $this->data[$hardpoint] = $value;
  }

  public function attachHardpoint($object, $hardpoint, $value) {
    if ($this->hasAttachedHardpoint($object, $hardpoint)) {
      throw new Exception(
        pht(
          'Hardpoint ("%s") already has attached data.',
          $hardpoint));
    }

    $this->setHardpointValue($object, $hardpoint, $value);
  }

  public function getHardpointListForDisplay() {
    $list = array_keys($this->hardpoints);

    if ($list) {
      sort($list);
      return implode(', ', $list);
    }

    return pht('<none>');
  }

}
