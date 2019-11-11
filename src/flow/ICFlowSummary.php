<?php

final class ICFlowSummary extends PhutilConsoleView {

  private $workspace;
  private $branchLength = 2;
  private $fields = array();

  public function setWorkspace(ICFlowWorkspace $workspace) {
    $this->workspace = $workspace;
    return $this;
  }

  public function setBranchLength($length) {
    $this->branchLength = $length;
    return $this;
  }

  public function setFields(array $fields) {
    assert_instances_of($fields, 'ICFlowField');
    $this->fields = $fields;
    return $this;
  }

  public function getValues() {
    $flow = $this->workspace;
    $graph = $flow->getTrackingGraph();
    $nodes = $graph->getNodesInTopologicalOrder();
    $current_feature = $flow->getCurrentFeature();

    $flow
      ->loadRevisions()
      ->loadHeadDiffs()
      ->loadActiveDiffs();

    $features = array();
    foreach ($nodes as $branch) {
      if (in_array($branch, $flow->getFeatureNames())) {
        $features[] = $flow->getFeature($branch);
      }
    }
    ICFlowField::resolveFutures($this->fields, $flow);
    $feature_values = array();
    foreach ($features as $feature) {
      list($ahead, $behind) = $feature->getHead()->getTracking();
      $values = array(
        'tracking' => array(
          'upstream' => $graph->getUpstream($feature->getName()),
          'depth' => $graph->getDepth($feature->getName()),
          'ahead' => $ahead,
          'behind' => $behind,
        ),
      );
      foreach ($this->fields as $field) {
        $values['fields'][$field->getFieldKey()] = $field->getValues($feature);
      }
      $feature_values[$feature->getName()] = $values;
    }
    return $feature_values;
  }

  protected function drawView() {
    $table = (new PhutilConsoleTable())
      ->setShowHeader(false)
      ->setPadding(1)
      ->addColumn('current', array(
        'title' => '',
        'align' => PhutilConsoleTable::ALIGN_CENTER,
      ))
      ->addColumn('name',    array('title' => ''));

    foreach ($this->fields as $field) {
      $table->addColumn($field->getFieldKey(), $field->getTableColumn());
    }

    $flow = $this->workspace;
    $graph = $flow->getTrackingGraph();
    $nodes = $graph->getNodesInTopologicalOrder();
    $current_feature = $flow->getCurrentFeature();

    if (empty($nodes)) {
      $instructions = $current_feature
        ?
          pht(
            'Issue `%s` in order to create a new local tracking branch '.
            'starting from your current branch (%s).',
            'arc flow [branchname] [upstream_branch]',
            $current_feature->getName())
        :
          '';
      return tsprintf(
        "%s\n%s\n",
        pht('No local tracking branches.'),
        $instructions);
    }

    $flow
      ->loadRevisions()
      ->loadHeadDiffs()
      ->loadActiveDiffs();

    $features = array();
    foreach ($nodes as $branch) {
      if (in_array($branch, $flow->getFeatureNames())) {
        $features[] = $flow->getFeature($branch);
      }
    }
    ICFlowField::resolveFutures($this->fields, $flow);
    $profiler = PhutilServiceProfiler::getInstance();
    $id = $profiler->beginServiceCall(array(
      'type' => 'flow-summary',
    ));
    foreach ($features as $feature) {
      $data = array();
      $ref = $feature->getHead();
      $data['current'] = $this->renderCurrent($feature);
      $data['name'] = $this->renderBranch($feature, $graph, $nodes);
      foreach ($this->fields as $field) {
        if ($field->getFieldKey() == 'current') {
          continue;
        }
        $cell = $field->renderTableCell($feature);
        if ($ref->isHEAD()) {
          $cell = tsprintf('**%s**', $cell);
        }
        $data[$field->getFieldKey()] = $cell;
      }
      $table->addRow($data);
    }
    $profiler->endServiceCall($id, array());
    return $table;
  }

  private function renderCurrent(ICFlowFeature $feature) {
    if (isset($this->fields['current'])) {
      return $this->fields['current']->renderTableCell($feature);
    }
    return '';
  }

  private function renderBranch(
    ICFlowFeature $feature,
    ICGitBranchGraph $graph,
    array $nodes) {

    $branch = $feature->getName();
    $depth = $graph->getDepth($branch);
    $box_line = '';
    if ($depth) {
      $lower_siblings = $this->lowerSiblings($branch, $branch, $graph, $nodes);
      $connector = count($lower_siblings) ? '|-' : '|_';
      $box_line = $this->drawGraphColumn($connector, '-');
      $upstream = $branch;
      while ($upstream = $graph->getUpstream($upstream)) {
        if (!$graph->getUpstream($upstream)) {
          break;
        }
        $siblings = $this->lowerSiblings($upstream, $branch, $graph, $nodes);
        $column = count($siblings) ? '|' : ' ';
        $box_line = $this->drawGraphColumn($column).$box_line;
      }
    }

    list($ahead, $behind) = $feature->getHead()->getTracking();
    if ($behind || $ahead) {
      $separator = $ahead && $behind ? ':' : '';
      $behind = $behind ? $behind: '';
      $ahead = $ahead ? $ahead: '';
      $skew = tsprintf(
        '<fg:red>%s</fg>%s<fg:green>%s</fg>',
        $behind,
        $separator,
        $ahead);
    } else {
      $skew = '';
    }

    $ref = $feature->getHead();
    $display_branch = $branch;
    if ($ref->isHEAD()) {
      $display_branch = tsprintf('**%s**', $display_branch);
      $skew = tsprintf('**%s**', $skew);
    }

    return tsprintf('%s%s %s ', $box_line, $display_branch, $skew);
  }

  private function drawGraphColumn($start = ' ', $pad = ' ') {
    return ICBoxDrawing::draw($start.str_repeat($pad, $this->branchLength));
  }

  private function lowerSiblings(
    $check_branch,
    $current_branch,
    ICGitBranchGraph $graph,
    array $nodes) {

    $current_index = array_search($current_branch, $nodes);
    if ($current_index === count($nodes) - 1) {
      return array();
    }
    $lower_branches = array_slice($nodes, $current_index + 1);
    $siblings = $graph->getSiblings($check_branch);
    return array_intersect($lower_branches, $siblings);
  }

}
