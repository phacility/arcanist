<?php

/**
 * Format a Git ref selector. This formatting is important when executing
 * commands like "git log" which can not unambiguously parse all values as
 * ref selectors.
 *
 * Supports the following conversions:
 *
 *  %s Ref Selector
 *    Escapes a Git ref selector. In particular, this will reject ref selectors
 *    which Git may interpret as flags.
 *
 *  %R Raw String
 *    Passes text through unescaped.
 */
function gitsprintf($pattern /* , ... */) {
  $args = func_get_args();
  return xsprintf('xsprintf_git', null, $args);
}

/**
 * @{function:xsprintf} callback for Git encoding.
 */
function xsprintf_git($userdata, &$pattern, &$pos, &$value, &$length) {
  $type = $pattern[$pos];

  switch ($type) {
    case 's':

      // See T13589. Some Git commands accept both a ref selector and a list of
      // paths. For example:

      //   $ git log <ref> -- <path> <path> ...

      // These commands disambiguate ref selectors from paths using "--", but
      // have no mechanism for disambiguating ref selectors from flags.

      // Thus, there appears to be no way (in the general case) to safely
      // invoke these commands with an arbitrary ref selector string: ref
      // selector strings like "--flag" may be interpreted as flags, not as
      // ref selectors.

      // To resolve this, we reject any ref selector which begins with "-".
      // These selectors are never valid anyway, so there is no loss of overall
      // correctness. It would be more desirable to pass them to Git in a way
      // that guarantees Git inteprets the string as a ref selector, but it
      // appears that no mechanism exists to allow this.

      if (preg_match('(^-)', $value)) {
        throw new Exception(
          pht(
            'Git ref selector "%s" is not a valid selector and can not be '.
            'passed to the Git CLI safely in the general case.',
            $value));
      }
      break;
    case 'R':
      $type = 's';
      break;
  }

  $pattern[$pos] = $type;
}
