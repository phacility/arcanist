<?php

final class ArcanistGridRow
  extends Phobject {

  private $cells;

  public function setCells(array $cells) {
    $cells = id(new PhutilArrayCheck())
      ->setInstancesOf('ArcanistGridCell')
      ->setUniqueMethod('getKey')
      ->setContext($this, 'setCells')
      ->checkValue($cells);

    $this->cells = $cells;

    return $this;
  }

  public function getCells() {
    return $this->cells;
  }

  public function hasCell($key) {
    return isset($this->cells[$key]);
  }

  public function getCell($key) {
    if (!isset($this->cells[$key])) {
      throw new Exception(
        pht(
          'Row has no cell "%s".\n',
          $key));
    }

    return $this->cells[$key];
  }


}
