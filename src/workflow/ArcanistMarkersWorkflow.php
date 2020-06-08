<?php

abstract class ArcanistMarkersWorkflow
  extends ArcanistArcWorkflow {

  abstract protected function getWorkflowMarkerType();

  public function runWorkflow() {
    $api = $this->getRepositoryAPI();

    $marker_type = $this->getWorkflowMarkerType();

    $markers = $api->newMarkerRefQuery()
      ->withTypes(array($marker_type))
      ->execute();

    $states = array();
    foreach ($markers as $marker) {
      $state_ref = id(new ArcanistWorkingCopyStateRef())
        ->setCommitRef($marker->getCommitRef());

      $states[] = array(
        'marker' => $marker,
        'state' => $state_ref,
      );
    }

    $this->loadHardpoints(
      ipull($states, 'state'),
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS);

    $vectors = array();
    foreach ($states as $key => $state) {
      $marker_ref = $state['marker'];
      $state_ref = $state['state'];

      $vector = id(new PhutilSortVector())
        ->addInt($marker_ref->getIsActive() ? 1 : 0)
        ->addInt($marker_ref->getEpoch());

      $vectors[$key] = $vector;
    }

    $vectors = msortv($vectors, 'getSelf');
    $states = array_select_keys($states, array_keys($vectors));

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('active')
      ->addColumn('name')
      ->addColumn('status')
      ->addColumn('description');

    $rows = array();
    foreach ($states as $state) {
      $marker_ref = $state['marker'];
      $state_ref = $state['state'];
      $revision_ref = null;
      $commit_ref = $marker_ref->getCommitRef();

      $marker_name = tsprintf('**%s**', $marker_ref->getName());

      if ($state_ref->hasAmbiguousRevisionRefs()) {
        $status = pht('Ambiguous');
      } else {
        $revision_ref = $state_ref->getRevisionRef();
        if (!$revision_ref) {
          $status = tsprintf(
            '<fg:blue>%s</fg>',
            pht('No Revision'));
        } else {
          $status = $revision_ref->getStatusDisplayName();

          $ansi_color = $revision_ref->getStatusANSIColor();
          if ($ansi_color) {
            $status = tsprintf(
              sprintf('<fg:%s>%%s</fg>', $ansi_color),
              $status);
          }
        }
      }

      if ($revision_ref) {
        $description = $revision_ref->getFullName();
      } else {
        $description = $commit_ref->getSummary();
      }

      if ($marker_ref->getIsActive()) {
        $active_mark = '*';
      } else {
        $active_mark = ' ';
      }
      $is_active = tsprintf('** %s **', $active_mark);

      $rows[] = array(
        'active' => $is_active,
        'name' => $marker_name,
        'status' => $status,
        'description' => $description,
      );
    }

    $table->drawRows($rows);

    return 0;
  }

  final protected function hasMarkerTypeSupport($marker_type) {
    $api = $this->getRepositoryAPI();

    $types = $api->getSupportedMarkerTypes();
    $types = array_fuse($types);

    return isset($types[$marker_type]);
  }

}
