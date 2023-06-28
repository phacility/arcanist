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
  private $groupView;
  private $memberViews = array();

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

  public function setGroupView(ArcanistCommitGraphSetView $group_view) {
    $this->groupView = $group_view;
    return $this;
  }

  public function getGroupView() {
    return $this->groupView;
  }

  public function addMemberView(ArcanistCommitGraphSetView $member_view) {
    $this->memberViews[] = $member_view;
    return $this;
  }

  public function getMemberViews() {
    return $this->memberViews;
  }

  public function setMemberViews(array $member_views) {
    $this->memberViews = $member_views;
    return $this;
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

    $items = array();
    foreach ($object_layout as $layout) {
      $commit_ref = idx($layout, 'commit');
      if (!$commit_ref) {
        $items[] = $layout;
        continue;
      }

      $commit_hash = $commit_ref->getCommitHash();
      $markers = idx($marker_refs, $commit_hash);
      if (!$markers) {
        $items[] = $layout;
        continue;
      }

      $head_marker = array_shift($markers);
      $layout['marker'] = $head_marker;
      $items[] = $layout;

      if (!$markers) {
        continue;
      }

      foreach ($markers as $marker) {
        $items[] = array(
          'marker' => $marker,
        );
      }
    }

    $items = $this->collapseItems($items);

    $marker_view = $this->drawMarkerCell($items);
    $commits_view = $this->drawCommitsCell($items);
    $status_view = $this->drawStatusCell($items);
    $revisions_view = $this->drawRevisionsCell($items);
    $messages_view = $this->drawMessagesCell($items);

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

    $member_views = $this->getMemberViews();
    $member_count = count($member_views);
    if ($member_count > 1) {
      $items[] = array(
        'group' => $member_views,
      );
    }

    $terminal_width = phutil_console_get_terminal_width();
    $max_depth = (int)floor(3 + (max(0, $terminal_width - 72) / 6));

    $depth = $this->getViewDepth();

    if ($depth <= $max_depth) {
      $display_depth = ($depth * 2);
      $is_squished = false;
    } else {
      $display_depth = ($max_depth * 2);
      $is_squished = true;
    }

    $max_width = ($max_depth * 2) + 16;
    $available_width = $max_width - $display_depth;

    $mark_ne = "\xE2\x94\x97";
    $mark_ew = "\xE2\x94\x81";
    $mark_esw = "\xE2\x94\xB3";
    $mark_sw = "\xE2\x94\x93";
    $mark_bullet = "\xE2\x80\xA2";
    $mark_ns_light = "\xE2\x94\x82";
    $mark_ne_light = "\xE2\x94\x94";
    $mark_esw_light = "\xE2\x94\xAF";

    $has_children = $this->getChildViews();

    $is_first = true;
    $last_key = last_key($items);
    $cell = array();
    foreach ($items as $item_key => $item) {
      $marker_ref = idx($item, 'marker');
      $group_ref = idx($item, 'group');

      $is_last = ($item_key === $last_key);

      if ($marker_ref) {
        $marker_name = $marker_ref->getName();
        $is_active = $marker_ref->getIsActive();

        if ($is_active) {
          $marker_width = $available_width - 4;
        } else {
          $marker_width = $available_width;
        }

        $marker_name = id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs($marker_width)
          ->truncateString($marker_name);

        if ($marker_ref->getIsActive()) {
          $label = tsprintf(
            '<bg:green>**%s**</bg> **%s**',
            ' * ',
            $marker_name);
        } else {
          $label = tsprintf(
            '**%s**',
            $marker_name);
        }
      } else if ($group_ref) {
        $label = pht(
          '(... %s more revisions ...)',
          new PhutilNumber(count($group_ref) - 1));
      } else if ($is_first) {
        $label = $commit_label;
      } else {
        $label = '';
      }

      if ($display_depth > 2) {
        $indent = str_repeat(' ', $display_depth - 2);
      } else {
        $indent = '';
      }

      if ($is_first) {
        if ($display_depth === 0) {
          $path = $mark_bullet.' ';
        } else {
          if ($has_children) {
            $path = $mark_ne.$mark_ew.$mark_esw.' ';
          } else if (!$is_last) {
            $path = $mark_ne.$mark_ew.$mark_esw_light.' ';
          } else {
            $path = $mark_ne.$mark_ew.$mark_ew.' ';
          }
        }
      } else if ($group_ref) {
        $path = $mark_ne.'/'.$mark_sw.' ';
      } else {
        if ($is_last && !$has_children) {
          $path = $mark_ne_light.' ';
        } else {
          $path = $mark_ns_light.' ';
        }
        if ($display_depth > 0) {
          $path = '  '.$path;
        }
      }

      $indent_text = sprintf(
        '%s%s',
        $indent,
        $path);

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
      $count = idx($item, 'collapseCount');
      if ($count) {
        $cell[] = tsprintf("   :   \n");
        continue;
      }

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
      $count = idx($item, 'collapseCount');
      if ($count) {
        $cell[] = tsprintf(
          "%s\n",
          pht(
            '<... %s more commits ...>',
            new PhutilNumber($count)));
        continue;
      }

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

  private function collapseItems(array $items) {
    $show_context = 3;

    $map = array();
    foreach ($items as $key => $item) {
      $can_collapse =
        (isset($item['commit'])) &&
        (!isset($item['revision'])) &&
        (!isset($item['marker']));
      $map[$key] = $can_collapse;
    }

    $map = phutil_partition($map);
    foreach ($map as $partition) {
      $value = head($partition);

      if (!$value) {
        break;
      }

      $count = count($partition);
      if ($count < ($show_context * 2) + 3) {
        continue;
      }

      $partition = array_slice($partition, $show_context, -$show_context, true);

      $is_first = true;
      foreach ($partition as $key => $value) {
        if ($is_first) {
          $items[$key]['collapseCount'] = $count;
        } else {
          unset($items[$key]);
        }

        $is_first = false;
      }
    }

    return $items;
  }

}
