<?php

final class ICFlowMonogramField extends ICFlowField {

  public function getFieldKey() {
    return 'monogram';
  }

  public function getSummary() {
    return pht(
      'The short object name (DNNN) for the revision corresponding to HEAD of '.
      'the branch, if any.');
  }

  public function getDefaultFieldOrder() {
    return 3;
  }

  protected function renderValues(array $values) {
    return 'D'.idx($values, 'revision-id');
  }

  public function getValues(ICFlowFeature $feature) {
    $revision_id = $feature->getRevisionID();
    if ($revision_id) {
      return array('revision-id' => $revision_id);
    }
    return null;
  }

}
