<?php

abstract class ArcanistConfigurationSource
  extends Phobject {

  const SCOPE_USER = 'user';

  abstract public function getSourceDisplayName();
  abstract public function getAllKeys();
  abstract public function hasValueForKey($key);
  abstract public function getValueForKey($key);

  public function getConfigurationSourceScope() {
    return null;
  }

  public function isStringSource() {
    return false;
  }

  public function isWritableConfigurationSource() {
    return false;
  }

  public function didReadUnknownOption(ArcanistRuntime $runtime, $key) {
    $runtime->getLogEngine()->writeWarning(
      pht('UNKNOWN CONFIGURATION'),
      pht(
        'Ignoring unrecognized configuration option ("%s") from source: %s.',
        $key,
        $this->getSourceDisplayName()));
  }

}