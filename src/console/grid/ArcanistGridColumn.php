<?php

final class ArcanistGridColumn
  extends Phobject {

  private $key;
  private $alignment = self::ALIGNMENT_LEFT;
  private $displayWidth;
  private $minimumWidth;

  const ALIGNMENT_LEFT = 'align.left';
  const ALIGNMENT_CENTER = 'align.center';
  const ALIGNMENT_RIGHT = 'align.right';

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setAlignment($alignment) {
    $this->alignment = $alignment;
    return $this;
  }

  public function getAlignment() {
    return $this->alignment;
  }

  public function setDisplayWidth($display_width) {
    $this->displayWidth = $display_width;
    return $this;
  }

  public function getDisplayWidth() {
    return $this->displayWidth;
  }

  public function setMinimumWidth($minimum_width) {
    $this->minimumWidth = $minimum_width;
    return $this;
  }

  public function getMinimumWidth() {
    return $this->minimumWidth;
  }

}
