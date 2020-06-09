<?php

abstract class ArcanistRepositoryMarkerQuery
  extends ArcanistRepositoryQuery {

  private $isActive;
  private $markerTypes;
  private $names;
  private $commitHashes;
  private $ancestorCommitHashes;

  final public function withMarkerTypes(array $types) {
    $this->markerTypes = array_fuse($types);
    return $this;
  }

  final public function withNames(array $names) {
    $this->names = array_fuse($names);
    return $this;
  }

  final public function withIsActive($active) {
    $this->isActive = $active;
    return $this;
  }

  final public function execute() {
    $markers = $this->newRefMarkers();

    $api = $this->getRepositoryAPI();
    foreach ($markers as $marker) {
      $state_ref = id(new ArcanistWorkingCopyStateRef())
        ->setCommitRef($marker->getCommitRef());

      $marker->attachWorkingCopyStateRef($state_ref);

      $hash = $marker->getCommitHash();
      $hash = $api->getDisplayHash($hash);
      $marker->setDisplayHash($hash);
    }

    $types = $this->markerTypes;
    if ($types !== null) {
      foreach ($markers as $key => $marker) {
        if (!isset($types[$marker->getMarkerType()])) {
          unset($markers[$key]);
        }
      }
    }

    $names = $this->names;
    if ($names !== null) {
      foreach ($markers as $key => $marker) {
        if (!isset($names[$marker->getName()])) {
          unset($markers[$key]);
        }
      }
    }

    if ($this->isActive !== null) {
      foreach ($markers as $key => $marker) {
        if ($marker->getIsActive() !== $this->isActive) {
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
    if ($this->markerTypes === null) {
      return true;
    }

    return isset($this->markerTypes[$marker_type]);
  }

}
