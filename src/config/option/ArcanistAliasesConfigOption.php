<?php

final class ArcanistAliasesConfigOption
  extends ArcanistMultiSourceConfigOption {

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

    $results = array();
    foreach ($list as $spec) {
      $source = $spec->getConfigurationSource();
      $value = $spec->getValue();

      $value->setConfigurationSource($source);

      $results[] = $value;
    }

    return $results;
  }

  public function getDisplayValueFromValue($value) {
    return pht('Use the "alias" workflow to review aliases.');
  }

  public function getStorageValueFromValue($value) {
    return mpull($value, 'getStorageDictionary');
  }

}
