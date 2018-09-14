<?php

final class ArcanistWorkflowArgument
  extends Phobject {

  private $key;
  private $help;
  private $wildcard;

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

    return $spec;
  }

  public function setHelp($help) {
    $this->help = $help;
    return $this;
  }

  public function getHelp() {
    return $this->help;
  }

}

