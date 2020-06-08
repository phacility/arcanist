<?php

final class ArcanistMercurialRepositoryMarkerQuery
  extends ArcanistRepositoryMarkerQuery {

  protected function newRefMarkers() {
    $markers = array();

    if ($this->shouldQueryMarkerType(ArcanistMarkerRef::TYPE_BRANCH)) {
      $markers[] = $this->newBranchOrBookmarkMarkers(false);
    }

    if ($this->shouldQueryMarkerType(ArcanistMarkerRef::TYPE_BOOKMARK)) {
      $markers[] = $this->newBranchOrBookmarkMarkers(true);
    }

    return array_mergev($markers);
  }

  private function newBranchOrBookmarkMarkers($is_bookmarks) {
    $api = $this->getRepositoryAPI();

    $is_branches = !$is_bookmarks;

    // NOTE: This is a bit clumsy, but it allows us to get most bookmark and
    // branch information in a single command, including full hashes, without
    // using "--debug" or matching any human readable strings in the output.

    // NOTE: We can't get branches and bookmarks together in a single command
    // because if we query for "heads() + bookmark()", we can't tell if a
    // bookmarked result is a branch head or not.

    $template_fields = array(
      '{node}',
      '{branch}',
      '{join(bookmarks, "\3")}',
      '{activebookmark}',
      '{desc}',
    );
    $expect_fields = count($template_fields);

    $template = implode('\2', $template_fields).'\1';

    if ($is_bookmarks) {
      $query = hgsprintf('bookmark()');
    } else {
      $query = hgsprintf('head()');
    }

    $future = $api->newFuture(
      'log --rev %s --template %s --',
      $query,
      $template);

    list($lines) = $future->resolve();

    $markers = array();

    $lines = explode("\1", $lines);
    foreach ($lines as $line) {
      if (!strlen(trim($line))) {
        continue;
      }

      $fields = explode("\2", $line, $expect_fields);
      $actual_fields = count($fields);
      if ($actual_fields !== $expect_fields) {
        throw new Exception(
          pht(
            'Unexpected number of fields in line "%s", expected %s but '.
            'found %s.',
            $line,
            new PhutilNumber($expect_fields),
            new PhutilNumber($actual_fields)));
      }

      $node = $fields[0];

      $branch = $fields[1];
      if (!strlen($branch)) {
        $branch = 'default';
      }

      if ($is_bookmarks) {
        $bookmarks = $fields[2];
        if (strlen($bookmarks)) {
          $bookmarks = explode("\3", $fields[2]);
        } else {
          $bookmarks = array();
        }

        if (strlen($fields[3])) {
          $active_bookmark = $fields[3];
        } else {
          $active_bookmark = null;
        }
      } else {
        $bookmarks = array();
        $active_bookmark = null;
      }

      $message = $fields[4];
      $message_lines = phutil_split_lines($message, false);

      $commit_ref = $api->newCommitRef()
        ->setCommitHash($node)
        ->attachMessage($message);

      $template = id(new ArcanistMarkerRef())
        ->setCommitHash($node)
        ->setSummary(head($message_lines))
        ->attachCommitRef($commit_ref);

      if ($is_bookmarks) {
        foreach ($bookmarks as $bookmark) {
          $is_active = ($bookmark === $active_bookmark);

          $markers[] = id(clone $template)
            ->setMarkerType(ArcanistMarkerRef::TYPE_BOOKMARK)
            ->setName($bookmark)
            ->setIsActive($is_active);
        }
      }

      if ($is_branches) {
        $markers[] = id(clone $template)
          ->setMarkerType(ArcanistMarkerRef::TYPE_BRANCH)
          ->setName($branch);
      }
    }

    if ($is_branches) {
      $current_hash = $api->getCanonicalRevisionName('.');

      foreach ($markers as $marker) {
        if ($marker->getMarkerType() !== ArcanistMarkerRef::TYPE_BRANCH) {
          continue;
        }

        if ($marker->getCommitHash() === $current_hash) {
          $marker->setIsActive(true);
        }
      }
    }

    return $markers;
  }

}
