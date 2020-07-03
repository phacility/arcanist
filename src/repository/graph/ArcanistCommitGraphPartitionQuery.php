<?php

final class ArcanistCommitGraphPartitionQuery
  extends Phobject {

  private $graph;
  private $heads;
  private $hashes;

  public function setGraph(ArcanistCommitGraph $graph) {
    $this->graph = $graph;
    return $this;
  }

  public function getGraph() {
    return $this->graph;
  }

  public function withHeads(array $heads) {
    $this->heads = $heads;
    return $this;
  }

  public function withHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function execute() {
    $graph = $this->getGraph();

    $heads = $this->heads;
    $heads = array_fuse($heads);
    if (!$heads) {
      throw new Exception(pht('Partition query requires heads.'));
    }

    $waypoints = $heads;

    $stack = array();
    $partitions = array();
    $partition_identities = array();
    $n = 0;
    foreach ($heads as $hash) {
      $node = $graph->getNode($hash);

      if (!$node) {
        echo "TODO: WARNING: Bad hash {$hash}\n";
        continue;
      }

      $partitions[$hash] = $n;
      $partition_identities[$n] = array($n => $n);
      $n++;

      $stack[] = $node;
    }

    $scope = null;
    if ($this->hashes) {
      $scope = array_fuse($this->hashes);
    }

    $leaves = array();
    while ($stack) {
      $node = array_pop($stack);

      $node_hash = $node->getCommitHash();
      $node_partition = $partition_identities[$partitions[$node_hash]];

      $saw_parent = false;
      foreach ($node->getParentNodes() as $parent) {
        $parent_hash = $parent->getCommitHash();

        if ($scope !== null) {
          if (!isset($scope[$parent_hash])) {
            continue;
          }
        }

        $saw_parent = true;

        if (isset($partitions[$parent_hash])) {
          $parent_partition = $partition_identities[$partitions[$parent_hash]];

          // If we've reached this node from a child, it clearly is not a
          // head.
          unset($heads[$parent_hash]);

          // If we've reached a node which is already part of another
          // partition, we can stop following it and merge the partitions.

          $new_partition = $node_partition + $parent_partition;
          ksort($new_partition);

          if ($node_partition !== $new_partition) {
            foreach ($node_partition as $partition_id) {
              $partition_identities[$partition_id] = $new_partition;
            }
          }

          if ($parent_partition !== $new_partition) {
            foreach ($parent_partition as $partition_id) {
              $partition_identities[$partition_id] = $new_partition;
            }
          }
          continue;
        } else {
          $partitions[$parent_hash] = $partitions[$node_hash];
        }

        $stack[] = $parent;
      }

      if (!$saw_parent) {
        $leaves[$node_hash] = true;
      }
    }

    $partition_lists = array();
    $partition_heads = array();
    $partition_waypoints = array();
    $partition_leaves = array();
    foreach ($partitions as $hash => $partition) {
      $partition = reset($partition_identities[$partition]);
      $partition_lists[$partition][] = $hash;
      if (isset($heads[$hash])) {
        $partition_heads[$partition][] = $hash;
      }
      if (isset($waypoints[$hash])) {
        $partition_waypoints[$partition][] = $hash;
      }
      if (isset($leaves[$hash])) {
        $partition_leaves[$partition][] = $hash;
      }
    }

    $results = array();
    foreach ($partition_lists as $partition_id => $partition_list) {
      $partition_set = array_fuse($partition_list);

      $results[] = id(new ArcanistCommitGraphPartition())
        ->setGraph($graph)
        ->setHashes($partition_set)
        ->setHeads($partition_heads[$partition_id])
        ->setWaypoints($partition_waypoints[$partition_id])
        ->setTails($partition_leaves[$partition_id]);
    }

    return $results;
  }

}
