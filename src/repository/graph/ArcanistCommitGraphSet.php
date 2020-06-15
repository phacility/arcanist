<?php

final class ArcanistCommitGraphSet
  extends Phobject {

  private $setID;
  private $color;
  private $hashes;
  private $parentHashes;
  private $childHashes;
  private $parentSets;
  private $childSets;
  private $displayDepth;
  private $displayChildSets;

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setHashes($hashes) {
    $this->hashes = $hashes;
    return $this;
  }

  public function getHashes() {
    return $this->hashes;
  }

  public function setSetID($set_id) {
    $this->setID = $set_id;
    return $this;
  }

  public function getSetID() {
    return $this->setID;
  }

  public function setParentHashes($parent_hashes) {
    $this->parentHashes = $parent_hashes;
    return $this;
  }

  public function getParentHashes() {
    return $this->parentHashes;
  }

  public function setChildHashes($child_hashes) {
    $this->childHashes = $child_hashes;
    return $this;
  }

  public function getChildHashes() {
    return $this->childHashes;
  }

  public function setParentSets($parent_sets) {
    $this->parentSets = $parent_sets;
    return $this;
  }

  public function getParentSets() {
    return $this->parentSets;
  }

  public function setChildSets($child_sets) {
    $this->childSets = $child_sets;
    return $this;
  }

  public function getChildSets() {
    return $this->childSets;
  }

  public function setDisplayDepth($display_depth) {
    $this->displayDepth = $display_depth;
    return $this;
  }

  public function getDisplayDepth() {
    return $this->displayDepth;
  }

  public function setDisplayChildSets(array $display_child_sets) {
    $this->displayChildSets = $display_child_sets;
    return $this;
  }

  public function getDisplayChildSets() {
    return $this->displayChildSets;
  }

}
