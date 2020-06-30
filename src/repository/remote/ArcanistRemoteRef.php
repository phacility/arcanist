<?php

final class ArcanistRemoteRef
  extends ArcanistRef {

  private $remoteName;
  private $fetchURI;
  private $pushURI;

  public function getRefDisplayName() {
    return pht('Remote "%s"', $this->getRemoteName());
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

}
