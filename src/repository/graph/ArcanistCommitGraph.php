<?php

final class ArcanistCommitGraph
  extends Phobject {

  private $repositoryAPI;
  private $nodes = array();

  public function setRepositoryAPI(ArcanistRepositoryAPI $api) {
    $this->repositoryAPI = $api;
    return $this;
  }

  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function getNode($hash) {
    if (isset($this->nodes[$hash])) {
      return $this->nodes[$hash];
    } else {
      return null;
    }
  }

  public function getNodes() {
    return $this->nodes;
  }

  public function newQuery() {
    $api = $this->getRepositoryAPI();
    return $api->newCommitGraphQuery()
      ->setGraph($this);
  }

  public function newNode($hash) {
    if (isset($this->nodes[$hash])) {
      throw new Exception(
        pht(
          'Graph already has a node "%s"!',
          $hash));
    }

    $this->nodes[$hash] = id(new ArcanistCommitNode())
      ->setCommitHash($hash);

    return $this->nodes[$hash];
  }

  public function newPartitionQuery() {
    return id(new ArcanistCommitGraphPartitionQuery())
      ->setGraph($this);
  }

}
