<?php

final class ArcanistHardpointTaskResult
  extends Phobject {

  private $value;

  public function __construct($value) {
    $this->value = $value;
  }

  public function getValue() {
    return $this->value;
  }

}
