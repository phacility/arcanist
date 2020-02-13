<?php

final class ArcanistConfigurationSourceValue
  extends Phobject {

  private $source;
  private $value;

  public function __construct(ArcanistConfigurationSource $source, $value) {
    $this->source = $source;
    $this->value = $value;
  }

  public function getConfigurationSource() {
    return $this->source;
  }

  public function getValue() {
    return $this->value;
  }

}
