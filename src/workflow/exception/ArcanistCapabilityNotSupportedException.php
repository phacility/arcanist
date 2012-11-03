<?php

final class ArcanistCapabilityNotSupportedException extends Exception {

  public function __construct(ArcanistRepositoryAPI $api) {
    $name = $api->getSourceControlSystemName();
    parent::__construct(
      "This repository API ('{$name}') does not support the requested ".
      "capability.");
  }

}
