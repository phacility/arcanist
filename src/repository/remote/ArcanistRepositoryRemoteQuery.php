<?php

abstract class ArcanistRepositoryRemoteQuery
  extends ArcanistRepositoryQuery {

  private $names;

  final public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  final public function execute() {
    $refs = $this->newRemoteRefs();

    $names = $this->names;
    if ($names !== null) {
      $names = array_fuse($names);
      foreach ($refs as $key => $ref) {
        if (!isset($names[$ref->getRemoteName()])) {
          unset($refs[$key]);
        }
      }
    }

    return $refs;
  }

  abstract protected function newRemoteRefs();

}
