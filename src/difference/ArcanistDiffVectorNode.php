<?php

final class ArcanistDiffVectorNode
  extends Phobject {

  private $vector;
  private $children = array();
  private $parentNode;
  private $displayNode;
  private $displayVector;
  private $displayDepth;
  private $valueNode;
  private $attributes = array();

  public function setVector(array $vector) {
    $this->vector = $vector;
    return $this;
  }

  public function getVector() {
    return $this->vector;
  }

  public function getChildren() {
    return $this->children;
  }

  public function setParentNode(ArcanistDiffVectorNode $parent) {
    $this->parentNode = $parent;
    return $this;
  }

  public function getParentNode() {
    return $this->parentNode;
  }

  public function addChild(array $vector, $length, $idx) {
    $is_node = ($idx === ($length - 1));
    $element = $vector[$idx];

    if (!isset($this->children[$element])) {
      $this->children[$element] = id(new self())
        ->setParentNode($this)
        ->setVector(array_slice($vector, 0, $idx + 1));
    }

    $child = $this->children[$element];

    if ($is_node) {
      $child->setValueNode($child);
      return;
    }

    $child->addChild($vector, $length, $idx + 1);
  }

  public function getDisplayVector() {
    return $this->displayVector;
  }

  public function appendDisplayElement($element) {
    if ($this->displayVector === null) {
      $this->displayVector = array();
    }

    $this->displayVector[] = $element;

    return $this;
  }

  public function setDisplayNode(ArcanistDiffVectorNode $display_node) {
    $this->displayNode = $display_node;
    return $this;
  }

  public function getDisplayNode() {
    return $this->displayNode;
  }

  public function setDisplayDepth($display_depth) {
    $this->displayDepth = $display_depth;
    return $this;
  }

  public function getDisplayDepth() {
    return $this->displayDepth;
  }

  public function setValueNode($value_node) {
    $this->valueNode = $value_node;
    return $this;
  }

  public function getValueNode() {
    return $this->valueNode;
  }

  public function setAncestralAttribute($key, $value) {
    $this->attributes[$key] = $value;

    $parent = $this->getParentNode();
    if ($parent) {
      $parent->setAncestralAttribute($key, $value);
    }

    return $this;
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

}
