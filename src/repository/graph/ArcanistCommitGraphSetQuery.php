<?php

final class ArcanistCommitGraphSetQuery
  extends Phobject {

  private $partition;
  private $waypointMap;
  private $visitedDisplaySets;

  public function setPartition($partition) {
    $this->partition = $partition;
    return $this;
  }

  public function getPartition() {
    return $this->partition;
  }

  public function setWaypointMap(array $waypoint_map) {
    $this->waypointMap = $waypoint_map;
    return $this;
  }

  public function getWaypointMap() {
    return $this->waypointMap;
  }

  public function execute() {
    $partition = $this->getPartition();
    $graph = $partition->getGraph();

    $waypoint_color = array();
    $color = array();

    $waypoints = $this->getWaypointMap();
    foreach ($waypoints as $waypoint => $colors) {
      // TODO: Validate that "$waypoint" is in the partition.
      // TODO: Validate that "$colors" is a list of scalars.
      $waypoint_color[$waypoint] = $this->newColorFromRaw($colors);
    }

    $stack = array();

    $hashes = $partition->getTails();
    foreach ($hashes as $hash) {
      $stack[] = $graph->getNode($hash);

      if (isset($waypoint_color[$hash])) {
        $color[$hash] = $waypoint_color[$hash];
      } else {
        $color[$hash] = true;
      }
    }

    $partition_map = $partition->getHashes();

    $wait = array();
    foreach ($partition_map as $hash) {
      $node = $graph->getNode($hash);

      $incoming = $node->getParentNodes();
      if (count($incoming) < 2) {
        // If the node has one or fewer incoming edges, we can paint it as soon
        // as we reach it.
        continue;
      }

      // Discard incoming edges which aren't in the partition.
      $need = array();
      foreach ($incoming as $incoming_node) {
        $incoming_hash = $incoming_node->getCommitHash();

        if (!isset($partition_map[$incoming_hash])) {
          continue;
        }

        $need[] = $incoming_hash;
      }

      $need_count = count($need);
      if ($need_count < 2) {
        // If we have one or fewer incoming edges in the partition, we can
        // paint as soon as we reach the node.
        continue;
      }

      $wait[$hash] = $need_count;
    }

    while ($stack) {
      $node = array_pop($stack);
      $node_hash = $node->getCommitHash();

      $node_color = $color[$node_hash];

      $outgoing_nodes = $node->getChildNodes();

      foreach ($outgoing_nodes as $outgoing_node) {
        $outgoing_hash = $outgoing_node->getCommitHash();

        if (isset($waypoint_color[$outgoing_hash])) {
          $color[$outgoing_hash] = $waypoint_color[$outgoing_hash];
        } else if (isset($color[$outgoing_hash])) {
          $color[$outgoing_hash] = $this->newColorFromColors(
            $color[$outgoing_hash],
            $node_color);
        } else {
          $color[$outgoing_hash] = $node_color;
        }

        if (isset($wait[$outgoing_hash])) {
          $wait[$outgoing_hash]--;
          if ($wait[$outgoing_hash]) {
            continue;
          }
          unset($wait[$outgoing_hash]);
        }

        $stack[] = $outgoing_node;
      }
    }

    if ($wait) {
      throw new Exception(
        pht(
          'Did not reach every wait node??'));
    }

    // Now, we've colored the entire graph. Collect contiguous pieces of it
    // with the same color into sets.

    static $set_n = 1;

    $seen = array();
    $sets = array();
    foreach ($color as $hash => $node_color) {
      if (isset($seen[$hash])) {
        continue;
      }

      $seen[$hash] = true;

      $in_set = array();
      $in_set[$hash] = true;

      $stack = array();
      $stack[] = $graph->getNode($hash);

      while ($stack) {
        $node = array_pop($stack);
        $node_hash = $node->getCommitHash();

        $nearby = array();
        foreach ($node->getParentNodes() as $nearby_node) {
          $nearby[] = $nearby_node;
        }
        foreach ($node->getChildNodes() as $nearby_node) {
          $nearby[] = $nearby_node;
        }

        foreach ($nearby as $nearby_node) {
          $nearby_hash = $nearby_node->getCommitHash();

          if (isset($seen[$nearby_hash])) {
            continue;
          }

          if (idx($color, $nearby_hash) !== $node_color) {
            continue;
          }

          $seen[$nearby_hash] = true;
          $in_set[$nearby_hash] = true;
          $stack[] = $nearby_node;
        }
      }

      $set = id(new ArcanistCommitGraphSet())
        ->setSetID($set_n++)
        ->setColor($node_color)
        ->setHashes(array_keys($in_set));

      $sets[] = $set;
    }

    $set_map = array();
    foreach ($sets as $set) {
      foreach ($set->getHashes() as $hash) {
        $set_map[$hash] = $set;
      }
    }

    foreach ($sets as $set) {
      $parents = array();
      $children = array();

      foreach ($set->getHashes() as $hash) {
        $node = $graph->getNode($hash);

        foreach ($node->getParentNodes() as $edge => $ignored) {
          if (isset($set_map[$edge])) {
            if ($set_map[$edge] === $set) {
              continue;
            }
          }

          $parents[$edge] = true;
        }

        foreach ($node->getChildNodes() as $edge => $ignored) {
          if (isset($set_map[$edge])) {
            if ($set_map[$edge] === $set) {
              continue;
            }
          }

          $children[$edge] = true;
        }

        $parent_sets = array();
        foreach ($parents as $edge => $ignored) {
          if (!isset($set_map[$edge])) {
            continue;
          }

          $adjacent_set = $set_map[$edge];
          $parent_sets[$adjacent_set->getSetID()] = $adjacent_set;
        }

        $child_sets = array();
        foreach ($children as $edge => $ignored) {
          if (!isset($set_map[$edge])) {
            continue;
          }

          $adjacent_set = $set_map[$edge];
          $child_sets[$adjacent_set->getSetID()] = $adjacent_set;
        }
      }

      $set
        ->setParentHashes(array_keys($parents))
        ->setChildHashes(array_keys($children))
        ->setParentSets($parent_sets)
        ->setChildSets($child_sets);
    }

    $this->buildDisplayLayout($sets);

    return $sets;
  }

  private function newColorFromRaw($color) {
    return array_fuse($color);
  }

  private function newColorFromColors($u, $v) {
    if ($u === true) {
      return $v;
    }

    if ($v === true) {
      return $u;
    }

    return $u + $v;
  }

  private function buildDisplayLayout(array $sets) {
    $this->visitedDisplaySets = array();
    foreach ($sets as $set) {
      if (!$set->getParentSets()) {
        $this->visitDisplaySet($set);
      }
    }
  }

  private function visitDisplaySet(ArcanistCommitGraphSet $set) {
    // If at least one parent has not been visited yet, don't visit this
    // set. We want to put the set at the deepest depth it is reachable
    // from.
    foreach ($set->getParentSets() as $parent_id => $parent_set) {
      if (!isset($this->visitedDisplaySets[$parent_id])) {
        return false;
      }
    }

    $set_id = $set->getSetID();
    $this->visitedDisplaySets[$set_id] = true;

    $display_children = array();
    foreach ($set->getChildSets() as $child_id => $child_set) {
      $visited = $this->visitDisplaySet($child_set);
      if ($visited) {
        $display_children[$child_id] = $child_set;
      }
    }

    $set->setDisplayChildSets($display_children);

    return true;
  }


}
