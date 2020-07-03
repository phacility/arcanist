<?php

final class ArcanistGitRepositoryMarkerQuery
  extends ArcanistRepositoryMarkerQuery {

  protected function newLocalRefMarkers() {
    $api = $this->getRepositoryAPI();

    $future = $this->newCurrentBranchNameFuture()->start();

    $field_list = array(
      '%(refname)',
      '%(objectname)',
      '%(committerdate:raw)',
      '%(tree)',
      '%(*objectname)',
      '%(subject)',
      '%(subject)%0a%0a%(body)',
      '%02',
    );
    $expect_count = count($field_list);

    $branch_prefix = 'refs/heads/';
    $branch_length = strlen($branch_prefix);

    $remote_prefix = 'refs/remotes/';
    $remote_length = strlen($remote_prefix);

    list($stdout) = $api->newFuture(
      'for-each-ref --format %s -- refs/',
      implode('%01', $field_list))->resolve();

    $markers = array();

    $lines = explode("\2", $stdout);
    foreach ($lines as $line) {
      $line = trim($line);
      if (!strlen($line)) {
        continue;
      }

      $fields = explode("\1", $line, $expect_count);
      $actual_count = count($fields);
      if ($actual_count !== $expect_count) {
        throw new Exception(
          pht(
            'Unexpected field count when parsing line "%s", got %s but '.
            'expected %s.',
            $line,
            new PhutilNumber($actual_count),
            new PhutilNumber($expect_count)));
      }

      list($ref, $hash, $epoch, $tree, $dst_hash, $summary, $text) = $fields;

      $remote_name = null;

      if (!strncmp($ref, $branch_prefix, $branch_length)) {
        $type = ArcanistMarkerRef::TYPE_BRANCH;
        $name = substr($ref, $branch_length);
      } else if (!strncmp($ref, $remote_prefix, $remote_length)) {
        // This isn't entirely correct: the ref may be a tag, etc.
        $type = ArcanistMarkerRef::TYPE_BRANCH;

        $label = substr($ref, $remote_length);
        $parts = explode('/', $label, 2);

        $remote_name = $parts[0];
        $name = $parts[1];
      } else {
        // For now, discard other refs.
        continue;
      }

      $marker = id(new ArcanistMarkerRef())
        ->setName($name)
        ->setMarkerType($type)
        ->setEpoch((int)$epoch)
        ->setMarkerHash($hash)
        ->setTreeHash($tree)
        ->setSummary($summary)
        ->setMessage($text);

      if ($remote_name !== null) {
        $marker->setRemoteName($remote_name);
      }

      if (strlen($dst_hash)) {
        $commit_hash = $dst_hash;
      } else {
        $commit_hash = $hash;
      }

      $marker->setCommitHash($commit_hash);

      $commit_ref = $api->newCommitRef()
        ->setCommitHash($commit_hash)
        ->attachMessage($text);

      $marker->attachCommitRef($commit_ref);

      $markers[] = $marker;
    }

    $current = $this->resolveCurrentBranchNameFuture($future);

    if ($current !== null) {
      foreach ($markers as $marker) {
        if ($marker->getName() === $current) {
          $marker->setIsActive(true);
        }
      }
    }

    return $markers;
  }

  private function newCurrentBranchNameFuture() {
    $api = $this->getRepositoryAPI();
    return $api->newFuture('symbolic-ref --quiet HEAD --')
      ->setResolveOnError(true);
  }

  private function resolveCurrentBranchNameFuture($future) {
    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    $matches = null;
    if (!preg_match('(^refs/heads/(.*)\z)', trim($stdout), $matches)) {
      return null;
    }

    return $matches[1];
  }

  protected function newRemoteRefMarkers(ArcanistRemoteRef $remote) {
    $api = $this->getRepositoryAPI();

    // NOTE: Since we only care about branches today, we only list branches.

    $future = $api->newFuture(
      'ls-remote --refs %s %s',
      $remote->getRemoteName(),
      'refs/heads/*');
    list($stdout) = $future->resolve();

    $branch_prefix = 'refs/heads/';
    $branch_length = strlen($branch_prefix);

    $pattern = '(^(?P<hash>\S+)\t(?P<ref>\S+)\z)';
    $markers = array();

    $lines = phutil_split_lines($stdout, false);
    foreach ($lines as $line) {
      $matches = null;
      $ok = preg_match($pattern, $line, $matches);
      if (!$ok) {
        throw new Exception(
          pht(
            'Failed to match "ls-remote" pattern against line "%s".',
            $line));
      }

      $hash = $matches['hash'];
      $ref = $matches['ref'];

      if (!strncmp($ref, $branch_prefix, $branch_length)) {
        $type = ArcanistMarkerRef::TYPE_BRANCH;
        $name = substr($ref, $branch_length);
      } else {
        // For now, discard other refs.
        continue;
      }

      $marker = id(new ArcanistMarkerRef())
        ->setName($name)
        ->setMarkerType($type)
        ->setMarkerHash($hash)
        ->setCommitHash($hash);

      $commit_ref = $api->newCommitRef()
        ->setCommitHash($hash);

      $marker->attachCommitRef($commit_ref);

      $markers[] = $marker;
    }

    return $markers;
  }

}
