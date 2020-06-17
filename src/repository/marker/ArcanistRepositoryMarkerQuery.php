<?php

abstract class ArcanistRepositoryMarkerQuery
  extends ArcanistRepositoryQuery {

  private $isActive;
  private $markerTypes;
  private $names;
  private $commitHashes;
  private $ancestorCommitHashes;
  private $remotes;
  private $isRemoteCache = false;

  final public function withMarkerTypes(array $types) {
    $this->markerTypes = array_fuse($types);
    return $this;
  }

  final public function withNames(array $names) {
    $this->names = array_fuse($names);
    return $this;
  }

  final public function withRemotes(array $remotes) {
    assert_instances_of($remotes, 'ArcanistRemoteRef');
    $this->remotes = $remotes;
    return $this;
  }

  final public function withIsRemoteCache($is_cache) {
    $this->isRemoteCache = $is_cache;
    return $this;
  }

  final public function withIsActive($active) {
    $this->isActive = $active;
    return $this;
  }

  final public function execute() {
    $remotes = $this->remotes;
    if ($remotes !== null) {
      $marker_lists = array();
      foreach ($remotes as $remote) {
        $marker_list = $this->newRemoteRefMarkers($remote);
        foreach ($marker_list as $marker) {
          $marker->attachRemoteRef($remote);
        }
        $marker_lists[] = $marker_list;
      }
      $markers = array_mergev($marker_lists);
    } else {
      $markers = $this->newLocalRefMarkers();
      foreach ($markers as $marker) {
        $marker->attachRemoteRef(null);
      }
    }

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

    if ($this->isRemoteCache !== null) {
      $want_cache = $this->isRemoteCache;
      foreach ($markers as $key => $marker) {
        $is_cache = ($marker->getRemoteName() !== null);
        if ($is_cache !== $want_cache) {
          unset($markers[$key]);
        }
      }
    }

    return $markers;
  }

  final protected function shouldQueryMarkerType($marker_type) {
    if ($this->markerTypes === null) {
      return true;
    }

    return isset($this->markerTypes[$marker_type]);
  }

  abstract protected function newLocalRefMarkers();
  abstract protected function newRemoteRefMarkers(ArcanistRemoteRef $remote);

}
