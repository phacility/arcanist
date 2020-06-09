<?php

abstract class ArcanistRepositoryQuery
  extends Phobject {

  private $repositoryAPI;

  final public function setRepositoryAPI(ArcanistRepositoryAPI $api) {
    $this->repositoryAPI = $api;
    return $this;
  }

  final public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  abstract public function execute();

  final public function executeOne() {
    $refs = $this->execute();

    if (!$refs) {
      return null;
    }

    if (count($refs) > 1) {
      throw new Exception(
        pht(
          'Query matched multiple refs, expected zero or one.'));
    }

    return head($refs);
  }

}
