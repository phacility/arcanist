<?php

final class ArcanistCapabilityNotSupportedException extends Exception {

  public function __construct(ArcanistRepositoryAPI $api) {
    $name = $api->getSourceControlSystemName();
    parent::__construct(
      pht(
        "This repository API ('%s') does not support the requested capability.",
        $name));
  }

}
