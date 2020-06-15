<?php

final class ArcanistGridCell
  extends Phobject {

  private $key;
  private $content;
  private $contentWidth;
  private $contentHeight;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function getContentDisplayWidth() {
    $lines = $this->getContentDisplayLines();

    $width = 0;
    foreach ($lines as $line) {
      $width = max($width, phutil_utf8_console_strlen($line));
    }

    return $width;
  }

  public function getContentDisplayLines() {
    $content = $this->getContent();
    $content = tsprintf('%B', $content);
    $content = phutil_string_cast($content);

    $lines = phutil_split_lines($content, false);

    $result = array();
    foreach ($lines as $line) {
      $result[] = tsprintf('%R', $line);
    }

    return $result;
  }


}
