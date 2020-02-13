<?php

final class ArcanistAliasesConfigOption
  extends ArcanistListConfigOption {

  public function getType() {
    return 'list<alias>';
  }

  public function getValueFromStorageValue($value) {
    if (!is_array($value)) {
      throw new Exception(pht('Expected a list or dictionary!'));
    }

    $aliases = array();
    foreach ($value as $key => $spec) {
      $aliases[] = ArcanistAlias::newFromConfig($key, $spec);
    }

    return $aliases;
  }

  protected function didReadStorageValueList(array $list) {
    assert_instances_of($list, 'ArcanistConfigurationSourceValue');
    return mpull($list, 'getValue');
  }

  public function getDisplayValueFromValue($value) {
    return pht('Use the "alias" workflow to review aliases.');
  }

  public function getStorageValueFromValue($value) {
    return mpull($value, 'getStorageDictionary');
  }

}
