<?php

abstract class ArcanistRefInspector
  extends Phobject {

  abstract public function getInspectFunctionName();
  abstract public function newInspectRef(array $argv);

  final public static function getAllInspectors() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getInspectFunctionName')
      ->execute();
  }

}
