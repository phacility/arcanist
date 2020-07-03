<?php

final class ArcanistMercurialWorkEngine
  extends ArcanistWorkEngine {

  protected function getDefaultStartSymbol() {
    $api = $this->getRepositoryAPI();
    return $api->getWorkingCopyRevision();
  }

  protected function newMarker($symbol, $start) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    $log->writeStatus(
      pht('NEW BOOKMARK'),
      pht(
        'Creating new bookmark "%s" from "%s".',
        $symbol,
        $start));

    if ($start !== $this->getDefaultStartSymbol()) {
      $future = $api->newFuture('update -- %s', $start);
      $future->resolve();
    }

    $future = $api->newFuture('bookmark %s --', $symbol);
    $future->resolve();
  }

  protected function moveToMarker(ArcanistMarkerRef $marker) {
    $api = $this->getRepositoryAPI();
    $log = $this->getLogEngine();

    if ($marker->isBookmark()) {
      $log->writeStatus(
        pht('BOOKMARK'),
        pht(
          'Checking out bookmark "%s".',
          $marker->getName()));
    } else {
      $log->writeStatus(
        pht('BRANCH'),
        pht(
          'Checking out branch "%s".',
          $marker->getName()));
    }

    $future = $api->newFuture(
      'checkout %s --',
      $marker->getName());

    $future->resolve();
  }

}
