<?php

/**
 * This option type makes it easier to manage unknown options with unknown
 * types.
 */
final class ArcanistWildConfigOption
  extends ArcanistConfigOption {

  public function getType() {
    return 'wild';
  }

  public function getStorageValueFromStringValue($value) {
    return (string)$value;
  }

  public function getDisplayValueFromValue($value) {
    return json_encode($value);
  }

  public function getValueFromStorageValueList(array $list) {
    assert_instances_of($list, 'ArcanistConfigurationSourceValue');

    $source_value = last($list);
    $storage_value = $this->getStorageValueFromSourceValue($source_value);

    return $this->getValueFromStorageValue($storage_value);
  }

  public function getValueFromStorageValue($value) {
    return $value;
  }

  public function getStorageValueFromValue($value) {
    return $value;
  }

}
