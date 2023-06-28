<?php

abstract class ArcanistSingleSourceConfigOption
  extends ArcanistConfigOption {

  public function getValueFromStorageValueList(array $list) {
    assert_instances_of($list, 'ArcanistConfigurationSourceValue');

    $source_value = last($list);
    $storage_value = $this->getStorageValueFromSourceValue($source_value);

    return $this->getValueFromStorageValue($storage_value);
  }

  public function getValueFromStorageValue($value) {
    return $value;
  }

}
