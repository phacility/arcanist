<?php

abstract class ArcanistUnitFormatter
  extends Phobject {

  final public function getUnitFormatterKey() {
    return $this->getPhobjectClassConstant('FORMATTER_KEY');
  }

  public static function getAllUnitFormatters() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getUnitFormatterKey')
      ->execute();
  }

}
