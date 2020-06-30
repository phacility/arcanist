<?php

final class ArcanistRemoteRef
  extends ArcanistRef {

  private $repositoryAPI;
  private $remoteName;
  private $fetchURI;
  private $pushURI;

  const HARDPOINT_REPOSITORYREFS = 'arc.remote.repositoryRefs';

  public function getRefDisplayName() {
    return pht('Remote "%s"', $this->getRemoteName());
  }

  public function setRepositoryAPI(ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function setRemoteName($remote_name) {
    $this->remoteName = $remote_name;
    return $this;
  }

  public function getRemoteName() {
    return $this->remoteName;
  }

  public function setFetchURI($fetch_uri) {
    $this->fetchURI = $fetch_uri;
    return $this;
  }

  public function getFetchURI() {
    return $this->fetchURI;
  }

  public function setPushURI($push_uri) {
    $this->pushURI = $push_uri;
    return $this;
  }

  public function getPushURI() {
    return $this->pushURI;
  }

  protected function buildRefView(ArcanistRefView $view) {
    $view->setObjectName($this->getRemoteName());
  }

  protected function newHardpoints() {
    $object_list = new ArcanistObjectListHardpoint();
    return array(
      $this->newTemplateHardpoint(self::HARDPOINT_REPOSITORYREFS, $object_list),
    );
  }

  private function getRepositoryRefs() {
    return $this->getHardpoint(self::HARDPOINT_REPOSITORYREFS);
  }

  public function getPushRepositoryRef() {
    return $this->getRepositoryRefByURI($this->getPushURI());
  }

  public function getFetchRepositoryRef() {
    return $this->getRepositoryRefByURI($this->getFetchURI());
  }

  private function getRepositoryRefByURI($uri) {
    $api = $this->getRepositoryAPI();

    $uri = $api->getNormalizedURI($uri);
    foreach ($this->getRepositoryRefs() as $repository_ref) {
      foreach ($repository_ref->getURIs() as $repository_uri) {
        $repository_uri = $api->getNormalizedURI($repository_uri);
        if ($repository_uri === $uri) {
          return $repository_ref;
        }
      }
    }

    return null;
  }

  public function isPermanentRef(ArcanistMarkerRef $ref) {
    $repository_ref = $this->getPushRepositoryRef();
    if (!$repository_ref) {
      return false;
    }

    return $repository_ref->isPermanentRef($ref);
  }

}
