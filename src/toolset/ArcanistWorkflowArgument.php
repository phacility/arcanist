<?php

final class ArcanistWorkflowArgument
  extends Phobject {

  private $key;
  private $help;
  private $wildcard;
  private $parameter;
  private $isPathArgument;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setWildcard($wildcard) {
    $this->wildcard = $wildcard;
    return $this;
  }

  public function getWildcard() {
    return $this->wildcard;
  }

  public function getPhutilSpecification() {
    $spec = array(
      'name' => $this->getKey(),
    );

    if ($this->getWildcard()) {
      $spec['wildcard'] = true;
    }

    $parameter = $this->getParameter();
    if ($parameter !== null) {
      $spec['param'] = $parameter;
    }

    return $spec;
  }

  public function setHelp($help) {
    $this->help = $help;
    return $this;
  }

  public function getHelp() {
    return $this->help;
  }

  public function setParameter($parameter) {
    $this->parameter = $parameter;
    return $this;
  }

  public function getParameter() {
    return $this->parameter;
  }

  public function setIsPathArgument($is_path_argument) {
    $this->isPathArgument = $is_path_argument;
    return $this;
  }

  public function getIsPathArgument() {
    return $this->isPathArgument;
  }

}
