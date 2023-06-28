<?php

/**
 * Format a Mercurial revset expression. Supports the following conversions:
 *
 *  %s Symbol
 *    Escapes a Mercurial symbol, like a branch or bookmark name.
 *
 *  %R Rrraw Rreferrrence / Rrrrevset
 *    Passes text through unescaped (e.g., an already-escaped revset).
 */
function hgsprintf($pattern /* , ... */) {
  $args = func_get_args();
  return xsprintf('xsprintf_mercurial', null, $args);
}

/**
 * @{function:xsprintf} callback for Mercurial encoding.
 */
function xsprintf_mercurial($userdata, &$pattern, &$pos, &$value, &$length) {
  $type = $pattern[$pos];

  switch ($type) {
    case 's':
      // If this is symbol only has "safe" alphanumeric latin characters,
      // and is at least one character long, we can let it through without
      // escaping it. This tends to produce more readable commands.
      if (preg_match('(^[a-zA-Z0-9]+\z)', $value)) {
        $value = $value;
      } else {
        $value = "'".addcslashes($value, "'\\")."'";
      }
      break;
    case 'R':
      $type = 's';
      break;
  }

  $pattern[$pos] = $type;
}
