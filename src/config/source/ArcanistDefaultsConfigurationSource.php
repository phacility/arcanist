<?php

final class ArcanistDefaultsConfigurationSource
  extends ArcanistDictionaryConfigurationSource {

  public function getSourceDisplayName() {
    return pht('Builtin Defaults');
  }

  public function __construct() {
    $values = id(new ArcanistConfigurationEngine())
      ->newDefaults();

    parent::__construct($values);
  }

}
