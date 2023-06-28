<?php

/**
 * Format text for terminal output. This function behaves like `sprintf`,
 * except that all the normal conversions (like "%s") will be properly escaped,
 * and additional conversions are supported:
 *
 *   %B (Block)
 *     Escapes text, but preserves tabs and newlines.
 *
 *   %R (Raw String)
 *     Inserts raw, unescaped text. DANGEROUS!
 *
 * Particularly, this will escape terminal control characters.
 */
function tsprintf($pattern /* , ... */) {
  $args = func_get_args();
  $args[0] = PhutilConsoleFormatter::interpretFormat($args[0]);
  $string = xsprintf('xsprintf_terminal', null, $args);
  return new PhutilTerminalString($string);
}

/**
 * Callback for terminal encoding, see @{function:tsprintf} for use.
 */
function xsprintf_terminal($userdata, &$pattern, &$pos, &$value, &$length) {
  $type = $pattern[$pos];

  switch ($type) {
    case 's':
      $value = PhutilTerminalString::escapeStringValue($value, false);
      $type = 's';
      break;
    case 'B':
      $value = PhutilTerminalString::escapeStringValue($value, true);
      $type = 's';
      break;
    case 'R':
      $type = 's';
      break;
    case 'W':
      $value = PhutilTerminalString::escapeStringValue($value, true);
      $value = phutil_console_wrap($value);
      $type = 's';
      break;
    case '!':
      $value = tsprintf('<bg:yellow>** <!> %s **</bg>', $value);
      $value = PhutilTerminalString::escapeStringValue($value, false);
      $type = 's';
      break;
    case '?':
      $value = tsprintf('<bg:green>**  ?  **</bg> %s', $value);
      $value = PhutilTerminalString::escapeStringValue($value, false);
      $value = phutil_console_wrap($value, 6, false);
      $type = 's';
      break;
    case '>':
      $value = tsprintf("    **$ %s**\n", $value);
      $value = PhutilTerminalString::escapeStringValue($value, false);
      $type = 's';
      break;
    case 'd':
      $type = 'd';
      break;
    default:
      throw new Exception(
        pht(
          'Unsupported escape sequence "%s" found in pattern: %s',
          $type,
          $pattern));
      break;
  }

  $pattern[$pos] = $type;
}
