<?php

abstract class ArcanistUnitSink
  extends Phobject {

  private $results;

  final public function getUnitSinkKey() {
    return $this->getPhobjectClassConstant('SINKKEY');
  }

  public static function getAllUnitSinks() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getUnitSinkKey')
      ->execute();
  }

  public function sinkPartialResults(array $results) {
    return $this;
  }

  public function sinkFinalResults(array $results) {
    return $this;
  }

  public function getOutput() {
    return null;
  }

}
