<?php

final class ArcanistGitRepositoryRemoteQuery
  extends ArcanistRepositoryRemoteQuery {

  protected function newRemoteRefs() {
    $api = $this->getRepositoryAPI();

    $future = $api->newFuture('remote --verbose');
    list($lines) = $future->resolve();

    $pattern =
      '(^'.
      '(?P<name>[^\t]+)'.
      '\t'.
      '(?P<uri>[^\s]+)'.
      ' '.
      '\((?P<mode>fetch|push)\)'.
      '\z'.
      ')';

    $map = array();

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
      $mode = $matches['mode'];

      $map[$name][$mode] = $uri;
    }

    $refs = array();
    foreach ($map as $name => $uris) {
      $fetch_uri = idx($uris, 'fetch');
      $push_uri = idx($uris, 'push');

      $ref = id(new ArcanistRemoteRef())
        ->setRemoteName($name);

      if ($fetch_uri !== null) {
        $ref->setFetchURI($fetch_uri);
      }

      if ($push_uri !== null) {
        $ref->setPushURI($push_uri);
      }

      $refs[] = $ref;
    }

    return $refs;
  }

}
