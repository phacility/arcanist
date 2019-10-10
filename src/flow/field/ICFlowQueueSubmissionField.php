<?php

final class ICFlowQueueSubmissionField extends ICFlowField {

  public function getFieldKey() {
    return 'submit-id';
  }

  public function getSummary() {
    return pht(
      'The short object name (QSNNN) for the latest queue submission '.
      'corresponding to HEAD of the branch, if any.');
  }

  public function getDefaultFieldOrder() {
    return 2;
  }

  protected function renderValues(array $values) {
    return 'QS'.idx($values, 'submission-id');
  }

  public function getValues(ICFlowFeature $feature) {
    $submissions = $feature->getSearchAttachment('queue-submissions');
    if (!$submissions) {
      return null;
    }
    $status = $feature->getRevisionStatusName();
    if ($status === 'Closed') {
      return null;
    }
    $submission_ids = array();
    foreach ($submissions as $submission) {
      $submission_id = (int)$submission['id'];
      $submission_ids[$submission_id] = $submission_id;
    }
    ksort($submission_ids);
    $submission_id = last($submission_ids);
    return array('submission-id' => $submission_id);
  }

}
