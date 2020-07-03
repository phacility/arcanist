<?php

final class ArcanistRefView
  extends Phobject
  implements
    ArcanistTerminalStringInterface {

  private $objectName;
  private $title;
  private $ref;
  private $uri;
  private $lines = array();
  private $children = array();

  public function setRef(ArcanistRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function getObjectName() {
    return $this->objectName;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function addChild(ArcanistRefView $view) {
    $this->children[] = $view;
    return $this;
  }

  private function getChildren() {
    return $this->children;
  }

  public function appendLine($line) {
    $this->lines[] = $line;
    return $this;
  }

  public function newTerminalString() {
    return $this->newLines(0);
  }

  private function newLines($indent) {
    $ref = $this->getRef();

    $object_name = $this->getObjectName();
    $title = $this->getTitle();

    if ($object_name !== null) {
      $reserve_width = phutil_utf8_console_strlen($object_name) + 1;
    } else {
      $reserve_width = 0;
    }

    if ($indent) {
      $indent_text = str_repeat('  ', $indent);
    } else {
      $indent_text = '';
    }
    $indent_width = strlen($indent_text);

    $marker_width = 6;
    $display_width = phutil_console_get_terminal_width();

    $usable_width = ($display_width - $marker_width - $reserve_width);
    $usable_width = ($usable_width - $indent_width);

    // If the terminal is extremely narrow, don't degrade so much that the
    // output is completely unusable.
    $usable_width = max($usable_width, 16);

    // TODO: This should truncate based on console display width, not
    // glyphs, but there's currently no "setMaximumConsoleCharacterWidth()".

    $title = id(new PhutilUTF8StringTruncator())
      ->setMaximumGlyphs($usable_width)
      ->truncateString($title);

    if ($object_name !== null) {
      if (strlen($title)) {
        $display_text = tsprintf('**%s** %s', $object_name, $title);
      } else {
        $display_text = tsprintf('**%s**', $object_name);
      }
    } else {
      $display_text = $title;
    }

    $output = array();

    $output[] =  tsprintf(
      "<bg:cyan>**  *  **</bg> %s%s\n",
      $indent_text,
      $display_text);

    $uri = $this->getURI();
    if ($uri !== null) {
      $output[] = tsprintf(
        "<bg:cyan>** :// **</bg>   %s__%s__\n",
        $indent_text,
        $uri);
    }

    foreach ($this->lines as $line) {
      $output[] = tsprintf(
        "        %s%s\n",
        $indent_text,
        $line);
    }

    foreach ($this->getChildren() as $child) {
      foreach ($child->newLines($indent + 1) as $line) {
        $output[] = $line;
      }
    }

    return $output;
  }

}
