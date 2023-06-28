<?php

final class ArcanistGitWorkEngine
  extends ArcanistWorkEngine {

  protected function getDefaultStartSymbol() {
    $api = $this->getRepositoryAPI();

    // NOTE: In Git, we're trying to find the current branch name because the
    // behavior of "--track" depends on the symbol we pass.

    $marker = $api->newMarkerRefQuery()
      ->withIsActive(true)
      ->withMarkerTypes(array(ArcanistMarkerRef::TYPE_BRANCH))
      ->executeOne();
    if ($marker) {
      return $marker->getName();
    }

    return $api->getWorkingCopyRevision();
  }

  protected function newMarker($symbol, $start) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $log->writeStatus(
      pht('NEW BRANCH'),
      pht(
        'Creating new branch "%s" from "%s".',
        $symbol,
        $start));

    $future = $api->newFuture(
      'checkout --track -b %s %s --',
      $symbol,
      $start);
    $future->resolve();
  }

  protected function moveToMarker(ArcanistMarkerRef $marker) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $log->writeStatus(
      pht('BRANCH'),
      pht(
        'Checking out branch "%s".',
        $marker->getName()));

    $future = $api->newFuture(
      'checkout %s --',
      $marker->getName());
    $future->resolve();
  }

}
