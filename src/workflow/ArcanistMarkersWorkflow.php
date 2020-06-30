<?php

abstract class ArcanistMarkersWorkflow
  extends ArcanistArcWorkflow {

  private $nodes;

  abstract protected function getWorkflowMarkerType();

  public function runWorkflow() {
    $api = $this->getRepositoryAPI();

    $marker_type = $this->getWorkflowMarkerType();

    $markers = $api->newMarkerRefQuery()
      ->withMarkerTypes(array($marker_type))
      ->execute();

    $tail_hashes = $api->getPublishedCommitHashes();

    $heads = mpull($markers, 'getCommitHash');

    $graph = $api->getGraph();
    $limit = 1000;

    $query = $graph->newQuery()
      ->withHeadHashes($heads)
      ->setLimit($limit + 1);

    if ($tail_hashes) {
      $query->withTailHashes($tail_hashes);
    }

    $nodes = $query->execute();
    if (count($nodes) > $limit) {

      // TODO: Show what we can.

      throw new PhutilArgumentUsageException(
        pht(
          'Found more than %s unpublished commits which are ancestors of '.
          'heads.',
          new PhutilNumber($limit)));
    }

    // We may have some markers which point at commits which are already
    // published. These markers won't be reached by following heads backwards
    // until we reach published commits.

    // Load these markers exactly so they don't vanish in the output.

    // TODO: Mark these sets as published.

    $disjoint_heads = array();
    foreach ($heads as $head) {
      if (!isset($nodes[$head])) {
        $disjoint_heads[] = $head;
      }
    }

    if ($disjoint_heads) {
      $disjoint_nodes = $graph->newQuery()
        ->withExactHashes($disjoint_heads)
        ->execute();

      $nodes += $disjoint_nodes;
    }

    $state_refs = array();
    foreach ($nodes as $node) {
      $commit_ref = $node->getCommitRef();

      $state_ref = id(new ArcanistWorkingCopyStateRef())
        ->setCommitRef($commit_ref);

      $state_refs[$node->getCommitHash()] = $state_ref;
    }

    $this->loadHardpoints(
      $state_refs,
      ArcanistWorkingCopyStateRef::HARDPOINT_REVISIONREFS);

    $partitions = $graph->newPartitionQuery()
      ->withHeads($heads)
      ->withHashes(array_keys($nodes))
      ->execute();

    $revision_refs = array();
    foreach ($state_refs as $hash => $state_ref) {
      $revision_ids = mpull($state_ref->getRevisionRefs(), 'getID');
      $revision_refs[$hash] = array_fuse($revision_ids);
    }

    $partition_sets = array();
    $partition_vectors = array();
    foreach ($partitions as $partition_key => $partition) {
      $sets = $partition->newSetQuery()
        ->setWaypointMap($revision_refs)
        ->execute();

      list($sets, $partition_vector) = $this->sortSets(
        $graph,
        $sets,
        $markers);

      $partition_sets[$partition_key] = $sets;
      $partition_vectors[$partition_key] = $partition_vector;
    }

    $partition_vectors = msortv($partition_vectors, 'getSelf');
    $partitions = array_select_keys(
      $partitions,
      array_keys($partition_vectors));

    $partition_lists = array();
    foreach ($partitions as $partition_key => $partition) {
      $sets = $partition_sets[$partition_key];

      $roots = array();
      foreach ($sets as $set) {
        if (!$set->getParentSets()) {
          $roots[] = $set;
        }
      }

      // TODO: When no parent of a set is in the node list, we should render
      // a marker showing that the commit sequence is historic.

      $row_lists = array();
      foreach ($roots as $set) {
        $view = id(new ArcanistCommitGraphSetTreeView())
          ->setRepositoryAPI($api)
          ->setRootSet($set)
          ->setMarkers($markers)
          ->setStateRefs($state_refs);

        $row_lists[] = $view->draw();
      }
      $partition_lists[] = $row_lists;
    }

    $grid = id(new ArcanistGridView());
    $grid->newColumn('marker');
    $grid->newColumn('commits');
    $grid->newColumn('status');
    $grid->newColumn('revisions');
    $grid->newColumn('messages')
      ->setMinimumWidth(12);

    foreach ($partition_lists as $row_lists) {
      foreach ($row_lists as $row_list) {
        foreach ($row_list as $row) {
          $grid->newRow($row);
        }
      }
    }

    echo tsprintf('%s', $grid->drawGrid());
  }

  final protected function hasMarkerTypeSupport($marker_type) {
    $api = $this->getRepositoryAPI();

    $types = $api->getSupportedMarkerTypes();
    $types = array_fuse($types);

    return isset($types[$marker_type]);
  }

  private function sortSets(
    ArcanistCommitGraph $graph,
    array $sets,
    array $markers) {

    $marker_groups = mgroup($markers, 'getCommitHash');
    $sets = mpull($sets, null, 'getSetID');

    $active_markers = array();
    foreach ($sets as $set_id => $set) {
      foreach ($set->getHashes() as $hash) {
        $markers = idx($marker_groups, $hash, array());

        $has_active = false;
        foreach ($markers as $marker) {
          if ($marker->getIsActive()) {
            $has_active = true;
            break;
          }
        }

        if ($has_active) {
          $active_markers[$set_id] = $set;
          break;
        }
      }
    }

    $stack = array_select_keys($sets, array_keys($active_markers));
    while ($stack) {
      $cursor = array_pop($stack);
      foreach ($cursor->getParentSets() as $parent_id => $parent) {
        if (isset($active_markers[$parent_id])) {
          continue;
        }
        $active_markers[$parent_id] = $parent;
        $stack[] = $parent;
      }
    }

    $partition_epoch = 0;
    $partition_names = array();

    $vectors = array();
    foreach ($sets as $set_id => $set) {
      if (isset($active_markers[$set_id])) {
        $has_active = 1;
      } else {
        $has_active = 0;
      }

      $max_epoch = 0;
      $marker_names = array();
      foreach ($set->getHashes() as $hash) {
        $node = $graph->getNode($hash);
        $max_epoch = max($max_epoch, $node->getCommitEpoch());

        $markers = idx($marker_groups, $hash, array());
        foreach ($markers as $marker) {
          $marker_names[] = $marker->getName();
        }
      }

      $partition_epoch = max($partition_epoch, $max_epoch);

      if ($marker_names) {
        $has_markers = 1;
        natcasesort($marker_names);
        $max_name = last($marker_names);

        $partition_names[] = $max_name;
      } else {
        $has_markers = 0;
        $max_name = '';
      }


      $vector = id(new PhutilSortVector())
        ->addInt($has_active)
        ->addInt($max_epoch)
        ->addInt($has_markers)
        ->addString($max_name);

      $vectors[$set_id] = $vector;
    }

    $vectors = msortv_natural($vectors, 'getSelf');
    $vector_keys = array_keys($vectors);

    foreach ($sets as $set_id => $set) {
      $child_sets = $set->getDisplayChildSets();
      $child_sets = array_select_keys($child_sets, $vector_keys);
      $set->setDisplayChildSets($child_sets);
    }

    $sets = array_select_keys($sets, $vector_keys);

    if ($active_markers) {
      $any_active = true;
    } else {
      $any_active = false;
    }

    if ($partition_names) {
      $has_markers = 1;
      natcasesort($partition_names);
      $partition_name = last($partition_names);
    } else {
      $has_markers = 0;
      $partition_name = '';
    }

    $partition_vector = id(new PhutilSortVector())
      ->addInt($any_active)
      ->addInt($partition_epoch)
      ->addInt($has_markers)
      ->addString($partition_name);

    return array($sets, $partition_vector);
  }

}
