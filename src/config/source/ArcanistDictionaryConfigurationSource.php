<?php

abstract class ArcanistDictionaryConfigurationSource
  extends ArcanistConfigurationSource {

  private $values;

  public function __construct(array $dictionary) {
    $this->values = $dictionary;
  }

  public function getAllKeys() {
    return array_keys($this->values);
  }

  public function hasValueForKey($key) {
    return array_key_exists($key, $this->values);
  }

  public function getValueForKey($key) {
    if (!$this->hasValueForKey($key)) {
      throw new Exception(
        pht(
          'Configuration source ("%s") has no value for key ("%s").',
          get_class($this),
          $key));
    }

    return $this->values[$key];
  }

  public function setStorageValueForKey($key, $value) {
    $this->values[$key] = $value;

    $this->writeToStorage($this->values);

    return $this;
  }

  protected function writeToStorage($values) {
    throw new PhutilMethodNotImplementedException();
  }

}
