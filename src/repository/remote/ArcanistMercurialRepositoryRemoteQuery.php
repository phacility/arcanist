<?php

final class ArcanistMercurialRepositoryRemoteQuery
  extends ArcanistRepositoryRemoteQuery {

  protected function newRemoteRefs() {
    $api = $this->getRepositoryAPI();

    $future = $api->newFuture('paths');
    list($lines) = $future->resolve();

    $refs = array();

    $pattern = '(^(?P<name>.*?) = (?P<uri>.*)\z)';

    $lines = phutil_split_lines($lines, false);
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match($pattern, $line, $matches)) {
        throw new Exception(
          pht(
            'Failed to match remote pattern against line "%s".',
            $line));
      }

      $name = $matches['name'];
      $uri = $matches['uri'];

      // NOTE: Mercurial gives some special behavior to "default" and
      // "default-push", but these remotes are both fully-formed remotes that
      // are fetchable and pushable, they just have rules around selection
      // as default targets for operations.

      $ref = id(new ArcanistRemoteRef())
        ->setRemoteName($name)
        ->setFetchURI($uri)
        ->setPushURI($uri);

      $refs[] = $ref;
    }

    return $refs;
  }

}
