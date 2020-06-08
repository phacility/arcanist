<?php

abstract class ArcanistRepositoryMarkerQuery
  extends Phobject {

  private $repositoryAPI;
  private $types;
  private $commitHashes;
  private $ancestorCommitHashes;

  final public function setRepositoryAPI(ArcanistRepositoryAPI $api) {
    $this->repositoryAPI = $api;
    return $this;
  }

  final public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  final public function withTypes(array $types) {
    $this->types = array_fuse($types);
    return $this;
  }

  final public function execute() {
    $markers = $this->newRefMarkers();

    $types = $this->types;
    if ($types !== null) {
      foreach ($markers as $key => $marker) {
        if (!isset($types[$marker->getMarkerType()])) {
          unset($markers[$key]);
        }
      }
    }

    return $this->sortMarkers($markers);
  }

  private function sortMarkers(array $markers) {
    // Sort the list in natural order. If we apply a stable sort later,
    // markers will sort in "feature1", "feature2", etc., order if they
    // don't otherwise have a unique position.

    // This can improve behavior if two branches were updated at the same
    // time, as is common when cascading rebases after changes land.

    $map = mpull($markers, 'getName');
    natcasesort($map);
    $markers = array_select_keys($markers, array_keys($map));

    return $markers;
  }

  final protected function shouldQueryMarkerType($marker_type) {
    if ($this->types === null) {
      return true;
    }

    return isset($this->types[$marker_type]);
  }

}
