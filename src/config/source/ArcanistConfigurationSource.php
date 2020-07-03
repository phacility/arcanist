<?php

abstract class ArcanistConfigurationSource
  extends Phobject {

  const SCOPE_USER = 'user';
  const SCOPE_WORKING_COPY = 'working-copy';

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

    // TOOLSETS: Restore this warning once the new "arc" flow is in better
    // shape.
    return;

    $runtime->getLogEngine()->writeWarning(
      pht('UNKNOWN CONFIGURATION'),
      pht(
        'Ignoring unrecognized configuration option ("%s") from source: %s.',
        $key,
        $this->getSourceDisplayName()));
  }

}
