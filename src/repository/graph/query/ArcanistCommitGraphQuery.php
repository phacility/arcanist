<?php

abstract class ArcanistCommitGraphQuery
  extends Phobject {

  private $graph;
  private $headHashes;
  private $tailHashes;
  private $exactHashes;
  private $stopAtGCA;
  private $limit;

  final public function setGraph(ArcanistCommitGraph $graph) {
    $this->graph = $graph;
    return $this;
  }

  final public function getGraph() {
    return $this->graph;
  }

  final public function withHeadHashes(array $hashes) {
    $this->headHashes = $hashes;
    return $this;
  }

  final protected function getHeadHashes() {
    return $this->headHashes;
  }

  final public function withTailHashes(array $hashes) {
    $this->tailHashes = $hashes;
    return $this;
  }

  final protected function getTailHashes() {
    return $this->tailHashes;
  }

  final public function withExactHashes(array $hashes) {
    $this->exactHashes = $hashes;
    return $this;
  }

  final protected function getExactHashes() {
    return $this->exactHashes;
  }

  final public function withStopAtGCA($stop_gca) {
    $this->stopAtGCA = $stop_gca;
    return $this;
  }

  final public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  final protected function getLimit() {
    return $this->limit;
  }

  final public function getRepositoryAPI() {
    return $this->getGraph()->getRepositoryAPI();
  }

  abstract public function execute();

}
