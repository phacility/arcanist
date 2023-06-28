<?php

final class PhutilConsoleFormatter extends Phobject {

  private static $colorCodes = array(
    'black'   => 0,
    'red'     => 1,
    'green'   => 2,
    'yellow'  => 3,
    'blue'    => 4,
    'magenta' => 5,
    'cyan'    => 6,
    'white'   => 7,
    'default' => 9,
  );

  private static $disableANSI;

  public static function disableANSI($disable) {
    self::$disableANSI = $disable;
  }

  public static function getDisableANSI() {
    if (self::$disableANSI === null) {
      self::$disableANSI = self::newShouldDisableAnsi();
    }
    return self::$disableANSI;
  }

  private static function newShouldDisableANSI() {
    $term = phutil_utf8_strtolower(getenv('TERM'));

    // ansicon enables ANSI support on Windows
    if (!$term && getenv('ANSICON')) {
      $term = 'ansi';
    }


    if (phutil_is_windows()) {
      if ($term !== 'cygwin' && $term !== 'ansi') {
        return true;
      }
    }

    $stdout = PhutilSystem::getStdoutHandle();
    if ($stdout === null) {
      return true;
    }

    if (function_exists('posix_isatty')) {
      if (!posix_isatty($stdout)) {
        return true;
      }
    }

    return false;
  }

  public static function formatString($format /* ... */) {
    $args = func_get_args();
    $args[0] = self::interpretFormat($args[0]);
    return call_user_func_array('sprintf', $args);
  }

  public static function replaceColorCode($matches) {
    $codes = self::$colorCodes;
    $offset = 30 + $codes[$matches[2]];
    $default = 39;
    if ($matches[1] == 'bg') {
      $offset += 10;
      $default += 10;
    }

    return chr(27).'['.$offset.'m'.$matches[3].chr(27).'['.$default.'m';
  }

  public static function interpretFormat($format) {
    $colors = implode('|', array_keys(self::$colorCodes));

    // Sequence should be preceded by start-of-string or non-backslash
    // escaping.
    $bold_re      = '/(?<![\\\\])\*\*(.*)\*\*/sU';
    $underline_re = '/(?<![\\\\])__(.*)__/sU';
    $invert_re    = '/(?<![\\\\])##(.*)##/sU';

    if (self::getDisableANSI()) {
      $format = preg_replace($bold_re,      '\1',   $format);
      $format = preg_replace($underline_re, '\1',   $format);
      $format = preg_replace($invert_re,    '\1',   $format);
      $format = preg_replace(
        '@<(fg|bg):('.$colors.')>(.*)</\1>@sU',
        '\3',
        $format);
    } else {
      $esc        = chr(27);
      $bold       = $esc.'[1m'.'\\1'.$esc.'[m';
      $underline  = $esc.'[4m'.'\\1'.$esc.'[m';
      $invert     = $esc.'[7m'.'\\1'.$esc.'[m';

      $format = preg_replace($bold_re,      $bold,      $format);
      $format = preg_replace($underline_re, $underline, $format);
      $format = preg_replace($invert_re,    $invert,    $format);
      $format = preg_replace_callback(
        '@<(fg|bg):('.$colors.')>(.*)</\1>@sU',
        array(__CLASS__, 'replaceColorCode'),
        $format);
    }

    // Remove backslash escaping
    return preg_replace('/\\\\(\*\*.*\*\*|__.*__|##.*##)/sU', '\1', $format);
  }

}
