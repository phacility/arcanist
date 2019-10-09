<?php

final class ICFlowCurrentField extends ICFlowField {

  public function getFieldKey() {
    return 'current';
  }

  public function getSummary() {
    return pht(
      'Displays a dot denoting the currently checked out feature.');
  }

  protected function renderValues(array $values) {
    return idx($values, 'current') ?
      tsprintf('<fg:blue>%s</fg>', "\xE2\x97\x8E") :
      '';
  }

  public function getValues(ICFlowFeature $feature) {
    $ref = $feature->getHead();
    return array('current' => $ref->isHEAD());
  }

}
