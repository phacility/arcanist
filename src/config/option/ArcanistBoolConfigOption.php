<?php

final class ArcanistBoolConfigOption
  extends ArcanistSingleSourceConfigOption {

  public function getType() {
    return 'bool';
  }

  public function getStorageValueFromStringValue($value) {
    if ($value === 'true') {
      return true;
    }

    if ($value === 'false') {
      return false;
    }

    throw new PhutilArgumentUsageException(
      pht('Specify either "true" or "false".'));
  }

  public function getDisplayValueFromValue($value) {
    if ($value) {
      return 'true';
    } else {
      return 'false';
    }
  }

  public function getStorageValueFromValue($value) {
    return $value;
  }

}
