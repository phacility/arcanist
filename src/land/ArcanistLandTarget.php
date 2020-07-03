<?php

final class ArcanistLandTarget
  extends Phobject {

  private $remote;
  private $ref;
  private $commit;

  public function setRemote($remote) {
    $this->remote = $remote;
    return $this;
  }

  public function getRemote() {
    return $this->remote;
  }

  public function setRef($ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

  public function getLandTargetKey() {
    return sprintf('%s/%s', $this->getRemote(), $this->getRef());
  }

  public function setLandTargetCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getLandTargetCommit() {
    return $this->commit;
  }

}
