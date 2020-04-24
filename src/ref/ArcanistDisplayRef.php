<?php

final class ArcanistDisplayRef
  extends Phobject
  implements
    ArcanistTerminalStringInterface {

  private $ref;
  private $uri;

  public function setRef(ArcanistRef $ref) {
    $this->ref = $ref;
    return $this;
  }

  public function getRef() {
    return $this->ref;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function newTerminalString() {
    $ref = $this->getRef();

    if ($ref instanceof ArcanistDisplayRefInterface) {
      $object_name = $ref->getDisplayRefObjectName();
      $title = $ref->getDisplayRefTitle();
    } else {
      $object_name = null;
      $title = $ref->getRefDisplayName();
    }

    if ($object_name !== null) {
      $reserve_width = phutil_utf8_console_strlen($object_name) + 1;
    } else {
      $reserve_width = 0;
    }

    $marker_width = 6;
    $display_width = phutil_console_get_terminal_width();

    $usable_width = ($display_width - $marker_width - $reserve_width);

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

    $ref = $this->getRef();
    $output = array();

    $output[] =  tsprintf(
      "<bg:cyan>**  *  **</bg> %s\n",
      $display_text);

    $uri = $this->getURI();
    if ($uri !== null) {
      $output[] = tsprintf(
        "<bg:cyan>** :// **</bg>    __%s__\n",
        $uri);
    }

    return $output;
  }

}
