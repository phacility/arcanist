<?php

final class ArcanistWorkflowArgument
  extends Phobject {

  private $key;
  private $help;
  private $wildcard;
  private $parameter;
  private $isPathArgument;
  private $shortFlag;
  private $repeatable;

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

  public function setShortFlag($short_flag) {
    $this->shortFlag = $short_flag;
    return $this;
  }

  public function getShortFlag() {
    return $this->shortFlag;
  }

  public function setRepeatable($repeatable) {
    $this->repeatable = $repeatable;
    return $this;
  }

  public function getRepeatable() {
    return $this->repeatable;
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

    $help = $this->getHelp();
    if ($help !== null) {
      $spec['help'] = $help;
    }

    $short = $this->getShortFlag();
    if ($short !== null) {
      $spec['short'] = $short;
    }

    $repeatable = $this->getRepeatable();
    if ($repeatable !== null) {
      $spec['repeat'] = $repeatable;
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
