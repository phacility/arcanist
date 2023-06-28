<?php

final class ArcanistHardpointRequestList
  extends Phobject {

  private $requests;

  public static function newFromRequests(array $requests) {
    assert_instances_of($requests, 'ArcanistHardpointRequest');

    $object = new self();
    $object->requests = $requests;

    return $object;
  }

  public function getRequests() {
    return $this->requests;
  }

}
