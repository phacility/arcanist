<?php

final class ArcanistDifferentialDependencyGraph extends AbstractDirectedGraph {

  private $repositoryAPI;
  private $conduit;
  private $startPHID;

  public function setStartPHID($start_phid) {
    $this->startPHID = $start_phid;
    return $this;
  }
  public function getStartPHID() {
    return $this->startPHID;
  }

  public function setRepositoryAPI(ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }
  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function setConduit(ConduitClient $conduit) {
    $this->conduit = $conduit;
    return $this;
  }
  public function getConduit() {
    return $this->conduit;
  }

  protected function loadEdges(array $nodes) {
    $repository_api = $this->getRepositoryAPI();

    $dependencies = $this->getConduit()->callMethodSynchronous(
      'differential.query',
      array(
        'phids' => $nodes,
      ));

    $edges = array();
    foreach ($dependencies as $dependency) {
      $dependency_revision = $this->getCommitHashFromDict($dependency);
      if ($repository_api->hasLocalCommit($dependency_revision)) {
        $edges[$dependency['phid']] = array();
        continue;
      }
      $auxillary = idx($dependency, 'auxiliary', array());
      $edges[$dependency['phid']] = idx(
        $auxillary,
        'phabricator:depends-on',
        array());
    }
    return $edges;
  }

  private function getCommitHashFromDict($dict) {
    $api = $this->getRepositoryAPI();
    $hashes = idx($dict, 'hashes', array());
    if ($api instanceof ArcanistGitAPI) {
      $key = ArcanistDifferentialRevisionHash::HASH_GIT_COMMIT;
    } else if ($api instanceof ArcanistMercurialAPI) {
      $key = ArcanistDifferentialRevisionHash::HASH_MERCURIAL_COMMIT;
    } else {
      $key = null;
    }

    return idx($hashes, $key);
  }

}
