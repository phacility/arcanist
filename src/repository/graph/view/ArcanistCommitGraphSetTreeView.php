<?php

final class ArcanistCommitGraphSetTreeView
  extends Phobject {

  private $repositoryAPI;
  private $rootSet;
  private $markers;
  private $markerGroups;
  private $stateRefs;
  private $setViews;

  public function setRootSet($root_set) {
    $this->rootSet = $root_set;
    return $this;
  }

  public function getRootSet() {
    return $this->rootSet;
  }

  public function setMarkers($markers) {
    $this->markers = $markers;
    $this->markerGroups = mgroup($markers, 'getCommitHash');
    return $this;
  }

  public function getMarkers() {
    return $this->markers;
  }

  public function setStateRefs($state_refs) {
    $this->stateRefs = $state_refs;
    return $this;
  }

  public function getStateRefs() {
    return $this->stateRefs;
  }

  public function setRepositoryAPI($repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function draw() {
    $set = $this->getRootSet();

    $this->setViews = array();
    $view_root = $this->newSetViews($set);
    $view_list = $this->setViews;

    foreach ($view_list as $view) {
      $parent_view = $view->getParentView();
      if ($parent_view) {
        $depth = $parent_view->getViewDepth() + 1;
      } else {
        $depth = 0;
      }
      $view->setViewDepth($depth);
    }

    $api = $this->getRepositoryAPI();

    foreach ($view_list as $view) {
      $view_set = $view->getSet();
      $hashes = $view_set->getHashes();

      $commit_refs = $this->getCommitRefs($hashes);
      $revision_refs = $this->getRevisionRefs(head($hashes));
      $marker_refs = $this->getMarkerRefs($hashes);

      $view
        ->setRepositoryAPI($api)
        ->setCommitRefs($commit_refs)
        ->setRevisionRefs($revision_refs)
        ->setMarkerRefs($marker_refs);
    }

    $rows = array();
    foreach ($view_list as $view) {
      $rows[] = $view->newCellViews();
    }

    return $rows;
  }

  private function newSetViews(ArcanistCommitGraphSet $set) {
    $set_view = $this->newSetView($set);

    $this->setViews[] = $set_view;

    foreach ($set->getDisplayChildSets() as $child_set) {
      $child_view = $this->newSetViews($child_set);
      $child_view->setParentView($set_view);
      $set_view->addChildView($child_view);
    }

    return $set_view;
  }

  private function newSetView(ArcanistCommitGraphSet $set) {
    return id(new ArcanistCommitGraphSetView())
      ->setSet($set);
  }

  private function getStateRef($hash) {
    $state_refs = $this->getStateRefs();

    if (!isset($state_refs[$hash])) {
      throw new Exception(
        pht(
          'Found no state ref for hash "%s".',
          $hash));
    }

    return $state_refs[$hash];
  }

  private function getRevisionRefs($hash) {
    $state_ref = $this->getStateRef($hash);
    return $state_ref->getRevisionRefs();
  }

  private function getCommitRefs(array $hashes) {
    $results = array();
    foreach ($hashes as $hash) {
      $state_ref = $this->getStateRef($hash);
      $results[$hash] = $state_ref->getCommitRef();
    }

    return $results;
  }

  private function getMarkerRefs(array $hashes) {
    $results = array();
    foreach ($hashes as $hash) {
      $results[$hash] = idx($this->markerGroups, $hash, array());
    }
    return $results;
  }

}
