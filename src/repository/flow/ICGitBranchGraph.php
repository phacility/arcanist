<?php

final class ICGitBranchGraph extends AbstractDirectedGraph {

  public function getDepth($branch) {
    $depth = 0;
    while ($branch = $this->getUpstream($branch)) {
      $depth++;
    }
    return $depth;
  }

  public function getUpstream($branch) {
    foreach ($this->getNodes() as $upstream => $downstreams) {
      if (in_array($branch, $downstreams)) {
        return $upstream;
      }
    }
    return null;
  }

  public function getDownstreams($branch) {
    $downstreams = idx($this->getNodes(), $branch, array());
    array_multisort($downstreams);
    return $downstreams;
  }

  public function getSiblings($branch) {
    $parent = $this->getUpstream($branch);
    if (!$parent) {
      return array();
    }
    $siblings = $this->getDownstreams($parent);
    $branch_index = array_search($branch, $siblings);
    array_splice($siblings, $branch_index, 1);
    return $siblings;
  }

  protected function loadEdges(array $nodes) {
    $known = $this->getNodes();
    $edges = array();
    foreach ($nodes as $node) {
      $edges[$node] = idx($known, $node, array());
    }
    return $edges;
  }

}
