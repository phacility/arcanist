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

    $view_list = $this->collapseViews($view_root, $view_list);

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

  private function collapseViews($view_root, array $view_list) {
    $this->groupViews($view_root);

    foreach ($view_list as $view) {
      $group = $view->getGroupView();
      $group->addMemberView($view);
    }

    foreach ($view_list as $view) {
      $member_views = $view->getMemberViews();

      // Break small groups apart.
      $count = count($member_views);
      if ($count > 1 && $count < 4) {
        foreach ($member_views as $member_view) {
          $member_view->setGroupView($member_view);
          $member_view->setMemberViews(array($member_view));
        }
      }
    }

    foreach ($view_list as $view) {
      $parent_view = $view->getParentView();
      if (!$parent_view) {
        $depth = 0;
      } else {
        $parent_group = $parent_view->getGroupView();

        $member_views = $parent_group->getMemberViews();
        if (count($member_views) > 1) {
          $depth = $parent_group->getViewDepth() + 2;
        } else {
          $depth = $parent_group->getViewDepth() + 1;
        }
      }

      $view->setViewDepth($depth);
    }

    foreach ($view_list as $key => $view) {
      if (!$view->getMemberViews()) {
        unset($view_list[$key]);
      }
    }

    return $view_list;
  }

  private function groupViews($view) {
    $group_view = $this->getGroupForView($view);
    $view->setGroupView($group_view);



    $children = $view->getChildViews();
    foreach ($children as $child) {
      $this->groupViews($child);
    }
  }

  private function getGroupForView($view) {
    $revision_refs = $view->getRevisionRefs();
    if ($revision_refs) {
      $has_unpublished_revision = false;

      foreach ($revision_refs as $revision_ref) {
        if (!$revision_ref->isStatusPublished()) {
          $has_unpublished_revision = true;
          break;
        }
      }

      if ($has_unpublished_revision) {
        return $view;
      }
    }

    $marker_lists = $view->getMarkerRefs();
    foreach ($marker_lists as $marker_refs) {
      if ($marker_refs) {
        return $view;
      }
    }

    // If a view has no children, it is never grouped with other views.
    $children = $view->getChildViews();
    if (!$children) {
      return $view;
    }

    // If a view is a root, we can't group it.
    $parent = $view->getParentView();
    if (!$parent) {
      return $view;
    }

    // If a view has siblings, we can't group it with other views.
    $siblings = $parent->getChildViews();
    if (count($siblings) !== 1) {
      return $view;
    }

    // The view has no children and no other siblings, so add it to the
    // parent's group.
    return $parent->getGroupView();
  }

}
