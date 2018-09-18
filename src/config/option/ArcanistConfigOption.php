<?php

abstract class ArcanistConfigOption
  extends Phobject {

  private $key;
  private $help;
  private $summary;
  private $aliases = array();
  private $examples = array();
  private $defaultValue;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setAliases($aliases) {
    $this->aliases = $aliases;
    return $this;
  }

  public function getAliases() {
    return $this->aliases;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function setHelp($help) {
    $this->help = $help;
    return $this;
  }

  public function getHelp() {
    return $this->help;
  }

  public function setExamples(array $examples) {
    $this->examples = $examples;
    return $this;
  }

  public function getExamples() {
    return $this->examples;
  }

  public function setDefaultValue($default_value) {
    $this->defaultValue = $default_value;
    return $this;
  }

  public function getDefaultValue() {
    return $this->defaultValue;
  }

  abstract public function getType();

  abstract public function getValueFromStorageValueList(array $list);
  abstract public function getStorageValueFromStringValue($value);
  abstract public function getValueFromStorageValue($value);
  abstract public function getDisplayValueFromValue($value);

  protected function getStorageValueFromSourceValue(
    ArcanistConfigurationSourceValue $source_value) {

    $value = $source_value->getValue();
    $source = $source_value->getConfigurationSource();

    if ($source->isStringSource()) {
      $value = $this->getStorageValueFromStringValue($value);
    }

    return $value;
  }


}
