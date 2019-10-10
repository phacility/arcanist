<?php

final class ICConsoleTree extends PhutilConsoleView {
  private $table;
  private $treeColumn = null;
  private $children = array();
  private $parents = array();
  private $rows = array();
  private $hasAddedRows = false;
  private $branchLength = 3;

  public function setBranchLength($length) {
    $this->branchLength = $length;

    return $this;
  }

  public function setTable(PhutilConsoleTable $table) {
    if ($this->table) {
        throw new LogicException(
          'You cannot set the table for a console tree more than once, or '.
          'after adding columns.');
    }
    $this->table = $table;

    return $this;
  }

  private function getTable() {
    if (!$this->table) {
        $this->table = (new PhutilConsoleTable())
          ->setShowHeader(false);
    }

    return $this->table;
  }

  protected function drawView() {
    if (!$this->hasAddedRows) {
      foreach ($this->parents as $node => $parents) {
        if (!$parents) {
            $this->descend($node);
        }
      }
      $this->hasAddedRows = true;
    }

    return $this->table->drawView();
  }

  protected function descend($node, $depth = 0, array $open_depths = array(),
    $leaf = false) {

    $children = $this->children[$node];
    list($row, $meta) = $this->rows[$node];
    $meta_tree = idx($meta, $this->treeColumn);
    $suffix = idx($meta_tree, 'suffix', '');
    $tree = $this->formatTreeColumn($node, $depth, $open_depths, $leaf);
    $row[$this->treeColumn] = tsprintf('%s**%s**%s', $tree, $node, $suffix);
    $this->table->addRow($row);
    array_push($open_depths, $depth);
    while ($children) {
      $child = array_pop($children);
      if (!$children) {
        array_pop($open_depths);
      }
      $this->descend($child, $depth + 1, $open_depths, !$children);
    }
  }

  public static function drawTreeColumn($name, $depth, $leaf, $suffix) {
    $console = new self();
    $tree = $console->formatTreeColumn($name, $depth, array(), $leaf);
    return tsprintf('%s**%s**%s', $tree, $name, $suffix);
  }

  private function drawGraphColumn($start = ' ', $pad = ' ') {
    return ICBoxDrawing::draw($start.str_repeat($pad, $this->branchLength));
  }

  private function formatTreeColumn($node, $depth, array $open_depths, $leaf) {
    $tree = '';
    for ($i = 0; $i < $depth; ++$i) {
      if ($i === $depth - 1) {
        $tree .= $leaf ?
          $this->drawGraphColumn('|_', '-') :
          $this->drawGraphColumn('|-', '-');
      } else if (array_search($i, $open_depths) !== false) {
        $tree .= $this->drawGraphColumn('|');
      } else {
        $tree .= $this->drawGraphColumn();
      }
    }

    return $tree;
  }

  public function addColumn($key, array $column, $is_tree = false) {
    if ($is_tree && $this->treeColumn) {
      throw new LogicException('Only one column may be used to display the '.
                               'tree.');
    } else if ($is_tree) {
      $this->treeColumn = $key;
    }
    $this->getTable()->addColumn($key, $column);

    return $this;
  }

  public function addRow(array $data, $parent = null, array $meta = array()) {
    if (!$this->treeColumn) {
      throw new LogicException('You must add one column specified as the tree '.
                               'column.');
    }

    $node = idx($data, $this->treeColumn);
    if (!$node || idx($this->rows, $node)) {
      throw new LogicException('All rows must have a unique value for their '.
                               'tree column.');
    }
    $this->rows[$node] = array($data, $meta);
    if (!idx($this->children, $node)) {
      $this->children[$node] = array();
    }
    if ($parent) {
      $this->children[$parent][] = $node;
    }
    $this->parents[$node] = $parent;

    return $this;
  }
}
