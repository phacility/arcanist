<?php

final class ICBoxDrawing extends Phobject {

  private static $map = array(
    '-|' => ICGlyphLibrary::VERTICAL_LEFT,
    '|-' => ICGlyphLibrary::VERTICAL_RIGHT,
    '|' => ICGlyphLibrary::VERTICAL,
    '-' => ICGlyphLibrary::HORIZONTAL,
    '|_' => ICGlyphLibrary::UP_RIGHT,
  );

  private static $encodedMap = null;

  private static function encodedMap() {
    if (self::$encodedMap === null) {
      self::$encodedMap = array();
      foreach (self::$map as $map_code => $map_const) {
        self::$encodedMap[$map_code] = ICGlyphLibrary::encode($map_const);
      }
    }
    return self::$encodedMap;
  }

  public static function map($code) {
    return idx(self::encodedMap(), $code, $code);
  }

  public static function draw($str) {
    return strtr($str, self::encodedMap());
  }

}
