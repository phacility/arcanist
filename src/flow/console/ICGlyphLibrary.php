<?php

/**
 * If you use console glyphs somewhere, store them here so that it's not
 * impossible to discern what will be rendered when referencing a UTF-16
 * character.
 *
 * You can use a site like
 * http://www.fileformat.info/info/unicode/char/search.htm to find the
 * character you want.  Typically double byte characters are referred to
 * like this: "Unicode Character 'CROSS MARK' (U+274C)", resulting in a
 * two byte php string like this: "\x27\x4C".
 */
final class ICGlyphLibrary extends Phobject {

  /**
   * Box drawings.
   */
  const VERTICAL_LEFT = "\x25\x27";
  const VERTICAL_RIGHT = "\x25\x1C";
  const VERTICAL = "\x25\x02";
  const HORIZONTAL = "\x25\x00";
  const UP_RIGHT = "\x25\x14";

  /**
   * Status messages.
   */
  const CHECK_MARK = "\x27\x13";
  const CROSS_MARK = "\x27\x4C";
  const ANTICLOCKWISE_ARROW = "\x27\xF2";

  public static function encode($const) {
    return mb_convert_encoding($const, 'UTF-8', 'UTF-16BE');
  }
}
