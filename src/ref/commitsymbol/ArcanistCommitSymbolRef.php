<?php

final class ArcanistCommitSymbolRef
  extends ArcanistRef {

  private $symbol;

  const HARDPOINT_COMMIT = 'ref.commit-symbol';

  public function getRefDisplayName() {
    return pht('Commit Symbol "%s"', $this->getSymbol());
  }

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_COMMIT),
    );
  }

  public function setSymbol($symbol) {
    $this->symbol = $symbol;
    return $this;
  }

  public function getSymbol() {
    return $this->symbol;
  }

  public function attachCommit(ArcanistCommitRef $commit) {
    return $this->attachHardpoint(self::HARDPOINT_COMMIT, $commit);
  }

  public function getCommit() {
    return $this->getHardpoint(self::HARDPOINT_COMMIT);
  }

}
