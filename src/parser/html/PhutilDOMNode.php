<?php

final class PhutilDOMNode extends Phobject {

  private $content;
  private $tagName;
  private $children = array();
  private $attributes = array();
  private $parentNode;
  private $rawHead;
  private $rawTail;

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function getContent() {
    return $this->content;
  }

  public function isContentNode() {
    return ($this->content !== null);
  }

  public function setTagName($tag_name) {
    $this->tagName = $tag_name;
    return $this;
  }

  public function getTagName() {
    return $this->tagName;
  }

  public function appendChild(PhutilDOMNode $node) {
    $node->parentNode = $this;
    $this->children[] = $node;
    return $this;
  }

  public function getChildren() {
    return $this->children;
  }

  public function getParentNode() {
    return $this->parentNode;
  }

  public function setAttributes(array $attributes) {
    $this->attributes = $attributes;
    return $this;
  }

  public function getAttributes() {
    return $this->attributes;
  }

  public function setRawHead($raw_string) {
    $this->rawHead = $raw_string;
    return $this;
  }

  public function setRawTail($raw_tail) {
    $this->rawTail = $raw_tail;
    return $this;
  }

  public function newRawString() {
    $raw = array();
    $raw[] = $this->rawHead;

    foreach ($this->getChildren() as $child) {
      $raw[] = $child->newRawString();
    }

    $raw[] = $this->rawTail;

    return implode('', $raw);
  }

  public function toDictionary() {
    if ($this->isContentNode()) {
      return array(
        'content' => $this->content,
      );
    } else {
      $children = array();

      foreach ($this->getChildren() as $child) {
        $children[] = $child->toDictionary();
      }

      return array(
        'tag' => $this->getTagName(),
        'attributes' => $this->getAttributes(),
        'children' => $children,
      );
    }
  }

  /**
   * Get a list of the children of a given DOM node, treating unexpected
   * tags as if they were raw content.
   */
  public function selectChildrenWithTags(array $tag_list) {
    $tag_map = array_fuse($tag_list);

    $nodes = array();
    foreach ($this->getChildren() as $child) {
      // If this is already a content node, just keep it as-is.
      if ($child->isContentNode()) {
        $nodes[] = $child;
        continue;
      }

      $tag_name = $child->getTagName();

      // If this is a tag that we're allowing, keep it as-is.
      if (isset($tag_map[$tag_name])) {
        $nodes[] = $child;
        continue;
      }

      // Otherwise, this is some other tag. Convert it into a content
      // node.

      $raw_string = $child->newRawString();

      $nodes[] = id(new self())
        ->setContent($raw_string)
        ->setRawHead($raw_string);
    }

    return $this->mergeContentNodes($nodes);
  }

  public function newRawContentString() {
    $content_node = $this->selectChildrenWithTags(array());

    if (!$content_node) {
      return '';
    }

    return head($content_node)->newRawString();
  }

  public function mergeContent() {
    $this->children = $this->mergeContentNodes($this->children);

    foreach ($this->getChildren() as $child) {
      $child->parentNode = $this;
      $child->mergeContent();
    }

    return $this;
  }

  /**
   * Given a list of nodes, combine sequences of multiple adjacent content
   * nodes into single nodes.
   */
  private function mergeContentNodes(array $nodes) {
    $list = array();
    $content_block = array();
    foreach ($nodes as $node) {
      if ($node->isContentNode()) {
        $content_block[] = $node;
        continue;
      }

      $list[] = $content_block;
      $content_block = array();

      $list[] = $node;
    }

    $list[] = $content_block;

    $results = array();
    foreach ($list as $item) {
      if (!is_array($item)) {
        $results[] = $item;
        continue;
      }

      if (!$item) {
        continue;
      }

      $parts = array();
      foreach ($item as $content_node) {
        $parts[] = $content_node->newRawString();
      }
      $parts = implode('', $parts);

      if (!strlen($parts)) {
        continue;
      }

      $results[] = id(new self())
        ->setContent($parts)
        ->setRawHead($parts);
    }

    return $results;
  }

}
