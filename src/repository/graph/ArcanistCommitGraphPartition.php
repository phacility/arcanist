<?php

final class ArcanistCommitGraphPartition
  extends Phobject {

  private $graph;
  private $hashes = array();
  private $heads = array();
  private $tails = array();
  private $waypoints = array();

  public function setGraph(ArcanistCommitGraph $graph) {
    $this->graph = $graph;
    return $this;
  }

  public function getGraph() {
    return $this->graph;
  }

  public function setHashes(array $hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function getHashes() {
    return $this->hashes;
  }

  public function setHeads(array $heads) {
    $this->heads = $heads;
    return $this;
  }

  public function getHeads() {
    return $this->heads;
  }

  public function setTails($tails) {
    $this->tails = $tails;
    return $this;
  }

  public function getTails() {
    return $this->tails;
  }

  public function setWaypoints($waypoints) {
    $this->waypoints = $waypoints;
    return $this;
  }

  public function getWaypoints() {
    return $this->waypoints;
  }

  public function newSetQuery() {
    return id(new ArcanistCommitGraphSetQuery())
      ->setPartition($this);
  }

}
