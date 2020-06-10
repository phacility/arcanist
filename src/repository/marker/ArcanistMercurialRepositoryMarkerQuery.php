<?php

final class ArcanistMercurialRepositoryMarkerQuery
  extends ArcanistRepositoryMarkerQuery {

  protected function newLocalRefMarkers() {
    return $this->newMarkers();
  }

  protected function newRemoteRefMarkers(ArcanistRemoteRef $remote = null) {
    return $this->newMarkers($remote);
  }

  private function newMarkers(ArcanistRemoteRef $remote = null) {
    $api = $this->getRepositoryAPI();

    // In native Mercurial it is difficult to identify remote markers, and
    // complicated to identify local markers efficiently. We use an extension
    // to provide a command which works like "git for-each-ref" locally and
    // "git ls-remote" when given a remote.

    $argv = array();
    foreach ($api->getMercurialExtensionArguments() as $arg) {
      $argv[] = $arg;
    }
    $argv[] = 'arc-ls-markers';

    // NOTE: In remote mode, we're using passthru and a tempfile on this
    // because it's a remote command and may prompt the user to provide
    // credentials interactively. In local mode, we can just read stdout.

    if ($remote !== null) {
      $tmpfile = new TempFile();
      Filesystem::remove($tmpfile);

      $argv[] = '--output';
      $argv[] = phutil_string_cast($tmpfile);
    }

    $argv[] = '--';

    if ($remote !== null) {
      $argv[] = $remote->getRemoteName();
    }

    if ($remote !== null) {
      $passthru = $api->newPassthru('%Ls', $argv);

      $err = $passthru->execute();
      if ($err) {
        throw new Exception(
          pht(
            'Call to "hg arc-ls-markers" failed with error "%s".',
            $err));
      }

      $raw_data = Filesystem::readFile($tmpfile);
      unset($tmpfile);
    } else {
      $future = $api->newFuture('%Ls', $argv);
      list($raw_data) = $future->resolve();
    }

    $items = phutil_json_decode($raw_data);

    $markers = array();
    foreach ($items as $item) {
      if (!empty($item['isClosed'])) {
        // NOTE: For now, we ignore closed branch heads.
        continue;
      }

      $node = $item['node'];
      if (!$node) {
        // NOTE: For now, we ignore the virtual "current branch" marker.
        continue;
      }

      switch ($item['type']) {
        case 'branch':
          $marker_type = ArcanistMarkerRef::TYPE_BRANCH;
          break;
        case 'bookmark':
          $marker_type = ArcanistMarkerRef::TYPE_BOOKMARK;
          break;
        case 'commit':
          $marker_type = null;
          break;
        default:
          throw new Exception(
            pht(
              'Call to "hg arc-ls-markers" returned marker of unknown '.
              'type "%s".',
              $item['type']));
      }

      if ($marker_type === null) {
        // NOTE: For now, we ignore the virtual "head" marker.
        continue;
      }

      $commit_ref = $api->newCommitRef()
        ->setCommitHash($node);

      $marker_ref = id(new ArcanistMarkerRef())
        ->setName($item['name'])
        ->setCommitHash($node)
        ->attachCommitRef($commit_ref);

      if (isset($item['description'])) {
        $description = $item['description'];
        $commit_ref->attachMessage($description);

        $description_lines = phutil_split_lines($description, false);
        $marker_ref->setSummary(head($description_lines));
      }

      $marker_ref
        ->setMarkerType($marker_type)
        ->setIsActive(!empty($item['isActive']));

      $markers[] = $marker_ref;
    }

    return $markers;
  }

}
