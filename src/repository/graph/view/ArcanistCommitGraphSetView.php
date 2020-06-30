<?php

final class ArcanistCommitGraphSetView
  extends Phobject {

  private $repositoryAPI;
  private $set;
  private $parentView;
  private $childViews = array();
  private $commitRefs;
  private $revisionRefs;
  private $markerRefs;
  private $viewDepth;

  public function setRepositoryAPI(ArcanistRepositoryAPI $repository_api) {
    $this->repositoryAPI = $repository_api;
    return $this;
  }

  public function getRepositoryAPI() {
    return $this->repositoryAPI;
  }

  public function setSet(ArcanistCommitGraphSet $set) {
    $this->set = $set;
    return $this;
  }

  public function getSet() {
    return $this->set;
  }

  public function setParentView(ArcanistCommitGraphSetView $parent_view) {
    $this->parentView = $parent_view;
    return $this;
  }

  public function getParentView() {
    return $this->parentView;
  }

  public function addChildView(ArcanistCommitGraphSetView $child_view) {
    $this->childViews[] = $child_view;
    return $this;
  }

  public function setChildViews(array $child_views) {
    assert_instances_of($child_views, __CLASS__);
    $this->childViews = $child_views;
    return $this;
  }

  public function getChildViews() {
    return $this->childViews;
  }

  public function setCommitRefs($commit_refs) {
    $this->commitRefs = $commit_refs;
    return $this;
  }

  public function getCommitRefs() {
    return $this->commitRefs;
  }

  public function setRevisionRefs($revision_refs) {
    $this->revisionRefs = $revision_refs;
    return $this;
  }

  public function getRevisionRefs() {
    return $this->revisionRefs;
  }

  public function setMarkerRefs($marker_refs) {
    $this->markerRefs = $marker_refs;
    return $this;
  }

  public function getMarkerRefs() {
    return $this->markerRefs;
  }

  public function setViewDepth($view_depth) {
    $this->viewDepth = $view_depth;
    return $this;
  }

  public function getViewDepth() {
    return $this->viewDepth;
  }

  public function newCellViews() {
    $set = $this->getSet();
    $api = $this->getRepositoryAPI();

    $commit_refs = $this->getCommitRefs();
    $revision_refs = $this->getRevisionRefs();
    $marker_refs = $this->getMarkerRefs();

    $merge_strings = array();
    foreach ($revision_refs as $revision_ref) {
      $summary = $revision_ref->getName();
      $merge_key = substr($summary, 0, 32);
      $merge_key = phutil_utf8_strtolower($merge_key);

      $merge_strings[$merge_key][] = $revision_ref;
    }

    $merge_map = array();
    foreach ($commit_refs as $commit_ref) {
      $summary = $commit_ref->getSummary();

      $merge_with = null;
      if (count($revision_refs) === 1) {
        $merge_with = head($revision_refs);
      } else {
        $merge_key = substr($summary, 0, 32);
        $merge_key = phutil_utf8_strtolower($merge_key);
        if (isset($merge_strings[$merge_key])) {
          $merge_refs = $merge_strings[$merge_key];
          if (count($merge_refs) === 1) {
            $merge_with = head($merge_refs);
          }
        }
      }

      if ($merge_with) {
        $revision_phid = $merge_with->getPHID();
        $merge_map[$revision_phid][] = $commit_ref;
      }
    }

    $revision_map = mpull($revision_refs, null, 'getPHID');

    $result_map = array();
    foreach ($merge_map as $merge_phid => $merge_refs) {
      if (count($merge_refs) !== 1) {
        continue;
      }

      $merge_ref = head($merge_refs);
      $commit_hash = $merge_ref->getCommitHash();

      $result_map[$commit_hash] = $revision_map[$merge_phid];
    }

    $object_layout = array();

    $merged_map = array_flip(mpull($result_map, 'getPHID'));
    foreach ($revision_refs as $revision_ref) {
      $revision_phid = $revision_ref->getPHID();
      if (isset($merged_map[$revision_phid])) {
        continue;
      }

      $object_layout[] = array(
        'revision' => $revision_ref,
      );
    }

    foreach ($commit_refs as $commit_ref) {
      $commit_hash = $commit_ref->getCommitHash();
      $revision_ref = idx($result_map, $commit_hash);

      $object_layout[] = array(
        'commit' => $commit_ref,
        'revision' => $revision_ref,
      );
    }

    $marker_layout = array();
    foreach ($object_layout as $layout) {
      $commit_ref = idx($layout, 'commit');
      if (!$commit_ref) {
        $marker_layout[] = $layout;
        continue;
      }

      $commit_hash = $commit_ref->getCommitHash();
      $markers = idx($marker_refs, $commit_hash);
      if (!$markers) {
        $marker_layout[] = $layout;
        continue;
      }

      $head_marker = array_shift($markers);
      $layout['marker'] = $head_marker;
      $marker_layout[] = $layout;

      if (!$markers) {
        continue;
      }

      foreach ($markers as $marker) {
        $marker_layout[] = array(
          'marker' => $marker,
        );
      }
    }

    $marker_view = $this->drawMarkerCell($marker_layout);
    $commits_view = $this->drawCommitsCell($marker_layout);
    $status_view = $this->drawStatusCell($marker_layout);
    $revisions_view = $this->drawRevisionsCell($marker_layout);
    $messages_view = $this->drawMessagesCell($marker_layout);

    return array(
      id(new ArcanistGridCell())
        ->setKey('marker')
        ->setContent($marker_view),
      id(new ArcanistGridCell())
        ->setKey('commits')
        ->setContent($commits_view),
      id(new ArcanistGridCell())
        ->setKey('status')
        ->setContent($status_view),
      id(new ArcanistGridCell())
        ->setKey('revisions')
        ->setContent($revisions_view),
      id(new ArcanistGridCell())
        ->setKey('messages')
        ->setContent($messages_view),
    );
  }

  private function drawMarkerCell(array $items) {
    $api = $this->getRepositoryAPI();
    $depth = $this->getViewDepth();

    $marker_refs = $this->getMarkerRefs();
    $commit_refs = $this->getCommitRefs();

    if (count($commit_refs) === 1) {
      $commit_ref = head($commit_refs);

      $commit_hash = $commit_ref->getCommitHash();
      $commit_hash = tsprintf(
        '%s',
        substr($commit_hash, 0, 7));

      $commit_label = $commit_hash;
    } else {
      $min = head($commit_refs);
      $max = last($commit_refs);
      $commit_label = tsprintf(
        '%s..%s',
        substr($min->getCommitHash(), 0, 7),
        substr($max->getCommitHash(), 0, 7));
    }

    $terminal_width = phutil_console_get_terminal_width();
    $max_depth = (int)floor(3 + (max(0, $terminal_width - 72) / 6));
    if ($depth <= $max_depth) {
      $indent = str_repeat(' ', ($depth * 2));
    } else {
      $more = ' ... ';
      $indent = str_repeat(' ', ($max_depth * 2) - strlen($more)).$more;
    }
    $indent .= '- ';

    $empty_indent = str_repeat(' ', strlen($indent));

    $max_width = ($max_depth * 2) + 16;
    $available_width = $max_width - (min($max_depth, $depth) * 2);

    $is_first = true;
    $cell = array();
    foreach ($items as $item) {
      $marker_ref = idx($item, 'marker');

      if ($marker_ref) {
        $marker_name = $marker_ref->getName();

        $marker_name = id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs($available_width)
          ->truncateString($marker_name);

        if ($marker_ref->getIsActive()) {
          $label = tsprintf(
            '<bg:green>**%s**</bg>',
            $marker_name);
        } else {
          $label = tsprintf(
            '**%s**',
            $marker_name);
        }
      } else if ($is_first) {
        $label = $commit_label;
      } else {
        $label = '';
      }

      if ($is_first) {
        $indent_text = $indent;
      } else {
        $indent_text = $empty_indent;
      }

      $cell[] = tsprintf(
        "%s%s\n",
        $indent_text,
        $label);

      $is_first = false;
    }

    return $cell;
  }

  private function drawCommitsCell(array $items) {
    $cell = array();
    foreach ($items as $item) {
      $commit_ref = idx($item, 'commit');
      if (!$commit_ref) {
        $cell[] = tsprintf("\n");
        continue;
      }

      $commit_label = $this->drawCommitLabel($commit_ref);
      $cell[] = tsprintf("%s\n", $commit_label);
    }

    return $cell;
  }

  private function drawCommitLabel(ArcanistCommitRef $commit_ref) {
    $api = $this->getRepositoryAPI();

    $hash = $commit_ref->getCommitHash();
    $hash = substr($hash, 0, 7);

    return tsprintf('%s', $hash);
  }

  private function drawRevisionsCell(array $items) {
    $cell = array();

    foreach ($items as $item) {
      $revision_ref = idx($item, 'revision');
      if (!$revision_ref) {
        $cell[] = tsprintf("\n");
        continue;
      }
      $revision_label = $this->drawRevisionLabel($revision_ref);
      $cell[] = tsprintf("%s\n", $revision_label);
    }

    return $cell;
  }

  private function drawRevisionLabel(ArcanistRevisionRef $revision_ref) {
    $api = $this->getRepositoryAPI();

    $monogram = $revision_ref->getMonogram();

    return tsprintf('%s', $monogram);
  }

  private function drawMessagesCell(array $items) {
    $cell = array();

    foreach ($items as $item) {
      $revision_ref = idx($item, 'revision');
      if ($revision_ref) {
        $cell[] = tsprintf("%s\n", $revision_ref->getName());
        continue;
      }

      $commit_ref = idx($item, 'commit');
      if ($commit_ref) {
        $cell[] = tsprintf("%s\n", $commit_ref->getSummary());
        continue;
      }

      $cell[] = tsprintf("\n");
    }

    return $cell;
  }

  private function drawStatusCell(array $items) {
    $cell = array();

    foreach ($items as $item) {
      $revision_ref = idx($item, 'revision');

      if (!$revision_ref) {
        $cell[] = tsprintf("\n");
        continue;
      }

      $revision_label = $this->drawRevisionStatus($revision_ref);
      $cell[] = tsprintf("%s\n", $revision_label);
    }

    return $cell;
  }


  private function drawRevisionStatus(ArcanistRevisionRef $revision_ref) {
    if (phutil_console_get_terminal_width() < 120) {
      $status = $revision_ref->getStatusShortDisplayName();
    } else {
      $status = $revision_ref->getStatusDisplayName();
    }

    $ansi_color = $revision_ref->getStatusANSIColor();
    if ($ansi_color) {
      $status = tsprintf(
        sprintf('<fg:%s>%%s</fg>', $ansi_color),
        $status);
    }

    return tsprintf('%s', $status);
  }


}
