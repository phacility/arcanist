<?php

abstract class ArcanistRefInspector
  extends Phobject {

  abstract public function getInspectFunctionName();
  abstract public function newInspectRef(array $argv);

  protected function newInspectors() {
    return array($this);
  }

  final public static function getAllInspectors() {
    $base_inspectors = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();

    $results = array();

    foreach ($base_inspectors as $base_inspector) {
      foreach ($base_inspector->newInspectors() as $inspector) {
        $results[] = $inspector;
      }
    }

    return mpull($results, null, 'getInspectFunctionName');
  }

}
