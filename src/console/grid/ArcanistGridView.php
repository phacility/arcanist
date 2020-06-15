<?php

final class ArcanistGridView
  extends Phobject {

  private $rows = array();
  private $columns = array();

  private $displayWidths = array();

  public function setColumns(array $columns) {
    assert_instances_of($columns, 'ArcanistGridColumn');
    $this->columns = $columns;
    return $this;
  }

  public function getColumns() {
    return $this->columns;
  }

  public function newColumn($key) {
    $column = id(new ArcanistGridColumn())
      ->setKey($key);

    $this->columns[$key] = $column;

    return $column;
  }

  public function newRow(array $cells) {
    assert_instances_of($cells, 'ArcanistGridCell');

    $row = id(new ArcanistGridRow())
      ->setCells($cells);

    $this->rows[] = $row;

    return $row;
  }

  public function drawGrid() {
    $columns = $this->getColumns();
    if (!$columns) {
      throw new Exception(
        pht(
          'Can not draw a grid with no columns!'));
    }

    $rows = array();
    foreach ($this->rows as $row) {
      $rows[] = $this->drawRow($row);
    }

    $rows = phutil_glue($rows, tsprintf("\n"));

    return tsprintf("%s\n", $rows);
  }

  private function getDisplayWidth($key) {
    if (!isset($this->displayWidths[$key])) {
      $column = $this->getColumn($key);

      $width = $column->getDisplayWidth();
      if ($width === null) {
        $width = 1;
        foreach ($this->getRows() as $row) {
          if (!$row->hasCell($key)) {
            continue;
          }

          $cell = $row->getCell($key);
          $width = max($width, $cell->getContentDisplayWidth());
        }
      }

      $this->displayWidths[$key] = $width;
    }

    return $this->displayWidths[$key];
  }

  public function getColumn($key) {
    if (!isset($this->columns[$key])) {
      throw new Exception(
        pht(
          'Grid has no column "%s".',
          $key));
    }

    return $this->columns[$key];
  }

  public function getRows() {
    return $this->rows;
  }

  private function drawRow(ArcanistGridRow $row) {
    $columns = $this->getColumns();

    $cells = $row->getCells();

    $out = array();
    $widths = array();
    foreach ($columns as $column_key => $column) {
      $display_width = $this->getDisplayWidth($column_key);

      $cell = idx($cells, $column_key);
      if ($cell) {
        $content = $cell->getContentDisplayLines();
      } else {
        $content = array('');
      }

      foreach ($content as $line_key => $line) {
        $line_width = phutil_utf8_console_strlen($line);

        if ($line_width === $display_width) {
          continue;
        }

        if ($line_width < $display_width) {
          $line = $this->padContentLineToWidth(
            $line,
            $line_width,
            $display_width,
            $column->getAlignment());
        } else if ($line_width > $display_width) {
          $line = $this->truncateContentLineToWidth(
            $line,
            $line_width,
            $display_width,
            $column->getAlignment());
        }

        $content[$line_key] = $line;
      }

      $out[] = $content;
      $widths[] = $display_width;
    }

    return $this->drawRowLayout($out, $widths);
  }

  private function drawRowLayout(array $raw_cells, array $display_widths) {
    $line_count = 0;
    foreach ($raw_cells as $key => $cells) {
      $raw_cells[$key] = array_values($cells);
      $line_count = max($line_count, count($cells));
    }

    $line_head = '';
    $cell_separator = '  ';
    $line_tail = '';

    $out = array();
    $cell_count = count($raw_cells);
    for ($ii = 0; $ii < $line_count; $ii++) {
      $line = array();
      for ($jj = 0; $jj < $cell_count; $jj++) {
        if (isset($raw_cells[$jj][$ii])) {
          $raw_line = $raw_cells[$jj][$ii];
        } else {
          $display_width = $display_widths[$jj];
          $raw_line = str_repeat(' ', $display_width);
        }
        $line[] = $raw_line;
      }

      $line = array(
        $line_head,
        phutil_glue($line, $cell_separator),
        $line_tail,
      );

      $out[] = $line;
    }

    $out = phutil_glue($out, tsprintf("\n"));

    return $out;
  }

  private function padContentLineToWidth(
    $line,
    $src_width,
    $dst_width,
    $alignment) {

    $delta = ($dst_width - $src_width);

    switch ($alignment) {
      case ArcanistGridColumn::ALIGNMENT_LEFT:
        $head = null;
        $tail = str_repeat(' ', $delta);
        break;
      case ArcanistGridColumn::ALIGNMENT_CENTER:
        $head_delta = (int)floor($delta / 2);
        $tail_delta = (int)ceil($delta / 2);

        if ($head_delta) {
          $head = str_repeat(' ', $head_delta);
        } else {
          $head = null;
        }

        if ($tail_delta) {
          $tail = str_repeat(' ', $tail_delta);
        } else {
          $tail = null;
        }
        break;
      case ArcanistGridColumn::ALIGNMENT_RIGHT:
        $head = str_repeat(' ', $delta);
        $tail = null;
        break;
      default:
        throw new Exception(
          pht(
            'Unknown column alignment "%s".',
            $alignment));
    }

    $result = array();

    if ($head !== null) {
      $result[] = $head;
    }

    $result[] = $line;

    if ($tail !== null) {
      $result[] = $tail;
    }

    return $result;
  }

  private function truncateContentLineToWidth(
    $line,
    $src_width,
    $dst_width,
    $alignment) {
    return $line;
  }

}
