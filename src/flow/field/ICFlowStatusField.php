<?php

final class ICFlowStatusField extends ICFlowField {

  public function getFieldKey() {
    return 'status';
  }

  public function getSummary() {
    return pht(
      'The review status or queue status of the differential revision '.
      'associated with HEAD of the branch.');
  }

  protected function renderValues(array $values) {
    $color = idx($values, 'color');
    $status = idx($values, 'status');
    return tsprintf("<fg:{$color}>%s</fg>", $status);
  }

  public function getValues(ICFlowFeature $feature) {
    $status = $feature->getRevisionStatusName();
    $submissions = $feature->getSearchAttachment('queue-submissions');
    if ($submissions) {
      $submissions = igroup($submissions, 'status');
      if (idx($submissions, 'applied')) {
        if ($status !== 'Closed') {
          $status = 'Queue Landing';
        }
      } else if (idx($submissions, 'queued')) {
        $status = 'In Queue';
      } else if (idx($submissions, 'rejected')) {
        $status = 'Queue Rejected';
      }
    }
    $color = idx(array(
      'Closed'          => 'cyan',
      'Needs Review'    => 'magenta',
      'Needs Revision'  => 'yellow',
      'Accepted'        => 'green',
      'Abandoned'       => 'default',
      'Deleted'         => 'red',
      'Queue Applied'   => 'green',
      'In Queue'        => 'blue',
      'Queue Rejected'  => 'red',
    ), $status, 'default');
    return array(
      'color' => $color,
      'status' => $status,
    );
  }

}
