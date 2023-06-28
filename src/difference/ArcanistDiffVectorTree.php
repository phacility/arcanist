<?php

final class ArcanistDiffVectorTree
  extends Phobject {

  private $vectors = array();

  public function addVector(array $vector) {
    $this->vectors[] = $vector;
    return $this;
  }

  public function newDisplayList() {
    $root = new ArcanistDiffVectorNode();

    foreach ($this->vectors as $vector) {
      $root->addChild($vector, count($vector), 0);
    }

    foreach ($root->getChildren() as $child) {
      $this->compressTree($child);
    }

    $root->setDisplayDepth(-1);
    foreach ($root->getChildren() as $child) {
      $this->updateDisplayDepth($child);
    }

    return $this->getDisplayList($root);
  }

  private function compressTree(ArcanistDiffVectorNode $node) {
    $display_node = $node;

    $children = $node->getChildren();
    if ($children) {
      $parent = $node->getParentNode();
      if ($parent) {
        $siblings = $parent->getChildren();
        if (count($siblings) === 1) {
          if (!$parent->getValueNode()) {
            $parent_display = $parent->getDisplayNode();
            if ($parent_display) {
              $display_node = $parent_display;
              if ($node->getValueNode()) {
                $parent->setValueNode($node->getValueNode());
              }
            }
          }
        }
      }
    }

    $node->setDisplayNode($display_node);

    $display_element = last($node->getVector());
    $display_node->appendDisplayElement($display_element);

    foreach ($children as $child) {
      $this->compressTree($child);
    }
  }

  private function updateDisplayDepth(ArcanistDiffVectorNode $node) {
    $parent_depth = $node->getParentNode()->getDisplayDepth();

    if ($node->getDisplayVector() === null) {
      $display_depth = $parent_depth;
    } else {
      $display_depth = $parent_depth + 1;
    }

    $node->setDisplayDepth($display_depth);

    foreach ($node->getChildren() as $child) {
      $this->updateDisplayDepth($child);
    }
  }

  private function getDisplayList(ArcanistDiffVectorNode $node) {
    $result = array();

    foreach ($node->getChildren() as $child) {
      if ($child->getDisplayVector() !== null) {
        $result[] = $child;
      }
      foreach ($this->getDisplayList($child) as $item) {
        $result[] = $item;
      }
    }

    return $result;
  }

}
