<?php

abstract class ArcanistRef
  extends ArcanistHardpointObject {

  abstract public function getRefDisplayName();

  final public function newDisplayRef() {
    return id(new ArcanistDisplayRef())
      ->setRef($this);
  }
}
