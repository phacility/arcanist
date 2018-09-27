<?php

final class ArcanistWorkingCopyPath
  extends Phobject {

  private $path;
  private $mode;
  private $data;
  private $binary;
  private $dataAsLines;
  private $charMap;
  private $lineMap;

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    if ($this->data === null) {
      throw new Exception(
        pht(
          'No data provided for path "%s".',
          $this->getDescription()));
    }

    return $this->data;
  }

  public function getDataAsLines() {
    if ($this->dataAsLines === null) {
      $lines = phutil_split_lines($this->getData());
      $this->dataAsLines = $lines;
    }

    return $this->dataAsLines;
  }

  public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function getMode() {
    if ($this->mode === null) {
      throw new Exception(
        pht(
          'No mode provided for path "%s".',
          $this->getDescription()));
    }

    return $this->mode;
  }

  public function isExecutable() {
    $mode = $this->getMode();
    return (bool)($mode & 0111);
  }

  public function isBinary() {
    if ($this->binary === null) {
      $data = $this->getData();
      $is_binary = ArcanistDiffUtils::isHeuristicBinaryFile($data);
      $this->binary = $is_binary;
    }

    return $this->binary;
  }

  public function getMimeType() {
    if ($this->mimeType === null) {
      // TOOLSETS: This is not terribly efficient on real repositories since
      // it re-writes files which are often already on disk, but is good for
      // unit tests.

      $tmp = new TempFile();
      Filesystem::writeFile($tmp, $this->getData());
      $mime = Filesystem::getMimeType($tmp);

      $this->mimeType = $mime;
    }

    return $this->mimeType;
  }


  public function getBasename() {
    return basename($this->getPath());
  }

  public function getLineAndCharFromOffset($offset) {
    if ($this->charMap === null) {
      $char_map = array();
      $line_map = array();

      $lines = $this->getDataAsLines();

      $line_number = 0;
      $line_start = 0;
      foreach ($lines as $line) {
        $len = strlen($line);
        $line_map[] = $line_start;
        $line_start += $len;
        for ($ii = 0; $ii < $len; $ii++) {
          $char_map[] = $line_number;
        }
        $line_number++;
      }

      $this->charMap = $char_map;
      $this->lineMap = $line_map;
    }

    $line = $this->charMap[$offset];
    $char = $offset - $this->lineMap[$line];

    return array($line, $char);
  }

}
