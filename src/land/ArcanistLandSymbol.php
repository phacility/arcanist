<?php

final class ArcanistLandSymbol
  extends Phobject {

  private $symbol;
  private $commit;

  public function setSymbol($symbol) {
    $this->symbol = $symbol;
    return $this;
  }

  public function getSymbol() {
    return $this->symbol;
  }

  public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getCommit() {
    return $this->commit;
  }

}
