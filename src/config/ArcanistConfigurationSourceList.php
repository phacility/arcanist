<?php

final class ArcanistConfigurationSourceList
  extends Phobject {

  private $sources = array();

  public function addSource(ArcanistConfigurationSource $source) {
    $this->sources[] = $source;
    return $this;
  }

}