<?php

abstract class ArcanistMultiSourceConfigOption
  extends ArcanistConfigOption {

  public function getValueFromStorageValueList(array $list) {
    assert_instances_of($list, 'ArcanistConfigurationSourceValue');

    $result_list = array();
    foreach ($list as $source_value) {
      $source = $source_value->getConfigurationSource();
      $storage_value = $this->getStorageValueFromSourceValue($source_value);

      $items = $this->getValueFromStorageValue($storage_value);
      foreach ($items as $item) {
        $result_list[] = new ArcanistConfigurationSourceValue(
          $source,
          $item);
      }
    }

    $result_list = $this->didReadStorageValueList($result_list);

    return $result_list;
  }

  protected function didReadStorageValueList(array $list) {
    assert_instances_of($list, 'ArcanistConfigurationSourceValue');
    return mpull($list, 'getValue');
  }

}
