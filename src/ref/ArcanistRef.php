<?php

abstract class ArcanistRef
  extends ArcanistHardpointObject {

  abstract public function getRefDisplayName();

  final public function newRefView() {
    $ref_view = id(new ArcanistRefView())
      ->setRef($this);

    $this->buildRefView($ref_view);

    return $ref_view;
  }

  protected function buildRefView(ArcanistRefView $view) {
    return null;
  }

}
