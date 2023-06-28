<?php

final class ArcanistPromptsConfigOption
  extends ArcanistMultiSourceConfigOption {

  public function getType() {
    return 'map<string, prompt>';
  }

  public function getValueFromStorageValue($value) {
    if (!is_array($value)) {
      throw new Exception(pht('Expected a list!'));
    }

    if (!phutil_is_natural_list($value)) {
      throw new Exception(pht('Expected a natural list!'));
    }

    $responses = array();
    foreach ($value as $spec) {
      $responses[] = ArcanistPromptResponse::newFromConfig($spec);
    }

    return $responses;
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
    return pht('Use the "prompts" workflow to review prompt responses.');
  }

  public function getStorageValueFromValue($value) {
    return mpull($value, 'getStorageDictionary');
  }

}
