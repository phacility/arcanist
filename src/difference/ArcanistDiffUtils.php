<?php

/**
 * Dumping ground for diff- and diff-algorithm-related miscellany.
 *
 * @group diff
 */
final class ArcanistDiffUtils {

  /**
   * Make a best-effort attempt to determine if a file is definitely binary.
   *
   * @return bool If true, the file is almost certainly binary. If false, the
   *              file might still be binary but is subtle about it.
   */
  public static function isHeuristicBinaryFile($data) {
    // Detect if a file is binary according to the Git heuristic, which is the
    // presence of NULL ("\0") bytes. Git only examines the first "few" bytes of
    // each file (8KB or so) as an optimization, but we don't have a reasonable
    // equivalent in PHP, so just look at all of it.
    return (strpos($data, "\0") !== false);
  }

  public static function renderDifferences(
    $old,
    $new,
    $context_lines = 3,
    $diff_options  = "-L 'Old Value' -L 'New Value'") {

    if ((string)$old === (string)$new) {
      $new .= "\n(Old and new values are identical.)";
    }

    $file_old = new TempFile();
    $file_new = new TempFile();

    Filesystem::writeFile($file_old, (string)$old."\n");
    Filesystem::writeFile($file_new, (string)$new."\n");

    list($err, $stdout) = exec_manual(
      'diff %C -U %s %s %s',
      $diff_options,
      $context_lines,
      $file_old,
      $file_new);

    return $stdout;
  }

  public static function generateIntralineDiff($o, $n) {
    if (!strlen($o) || !strlen($n)) {
      return array(
        array(array(0, strlen($o))),
        array(array(0, strlen($n)))
      );
    }

    // This algorithm is byte-oriented and thus not safe for UTF-8, so just
    // mark all the text as changed if either string has multibyte characters
    // in it. TODO: Fix this so that this algorithm is UTF-8 aware.
    if (preg_match('/[\x80-\xFF]/', $o.$n)) {
      return self::generateUTF8IntralineDiff($o, $n);
    }

    $result = self::buildLevenshteinDifferenceString($o, $n);

    do {
      $orig = $result;
      $result = preg_replace(
        '/([xdi])(s{3})([xdi])/',
        '$1xxx$3',
        $result);
      $result = preg_replace(
        '/([xdi])(s{2})([xdi])/',
        '$1xx$3',
        $result);
      $result = preg_replace(
        '/([xdi])(s{1})([xdi])/',
        '$1x$3',
        $result);
    } while ($result != $orig);

    $o_bright = array();
    $n_bright  = array();
    $rlen   = strlen($result);
    $len = -1;
    $cur = $result[0];
    $result .= '-';
    for ($ii = 0; $ii < strlen($result); $ii++) {
      $len++;
      $now = $result[$ii];
      if ($result[$ii] == $cur) {
        continue;
      }
      if ($cur == 's') {
        $o_bright[] = array(0, $len);
        $n_bright[] = array(0, $len);
      } else if ($cur == 'd') {
        $o_bright[] = array(1, $len);
      } else if ($cur == 'i') {
        $n_bright[] = array(1, $len);
      } else if ($cur == 'x') {
        $o_bright[] = array(1, $len);
        $n_bright[] = array(1, $len);
      }
      $cur = $now;
      $len = 0;
    }

    $o_bright = self::collapseIntralineRuns($o_bright);
    $n_bright = self::collapseIntralineRuns($n_bright);

    return array($o_bright, $n_bright);
  }

  public static function applyIntralineDiff($str, $intra_stack) {
    $buf = '';
    $p = $s = $e = 0; // position, start, end
    $highlight = $tag = $ent = false;
    $highlight_o = '<span class="bright">';
    $highlight_c = '</span>';

    $is_html = false;
    if ($str instanceof PhutilSafeHTML) {
      $is_html = true;
      $str = $str->getHTMLContent();
    }

    $n = strlen($str);
    for ($i = 0; $i < $n; $i++) {

      if ($p == $e) {
        do {
          if (empty($intra_stack)) {
            $buf .= substr($str, $i);
            break 2;
          }
          $stack = array_shift($intra_stack);
          $s = $e;
          $e += $stack[1];
        } while ($stack[0] == 0);
      }

      if (!$highlight && !$tag && !$ent && $p == $s) {
        $buf .= $highlight_o;
        $highlight = true;
      }

      if ($str[$i] == '<') {
        $tag = true;
        if ($highlight) {
          $buf .= $highlight_c;
        }
      }

      if (!$tag) {
        if ($str[$i] == '&') {
          $ent = true;
        }
        if ($ent && $str[$i] == ';') {
          $ent = false;
        }
        if (!$ent) {
          $p++;
        }
      }

      $buf .= $str[$i];

      if ($tag && $str[$i] == '>') {
        $tag = false;
        if ($highlight) {
          $buf .= $highlight_o;
        }
      }

      if ($highlight && ($p == $e || $i == $n - 1)) {
        $buf .= $highlight_c;
        $highlight = false;
      }
    }

    if ($is_html) {
      return phutil_safe_html($buf);
    }

    return $buf;
  }

  private static function collapseIntralineRuns($runs) {
    $count = count($runs);
    for ($ii = 0; $ii < $count - 1; $ii++) {
      if ($runs[$ii][0] == $runs[$ii + 1][0]) {
        $runs[$ii + 1][1] += $runs[$ii][1];
        unset($runs[$ii]);
      }
    }
    return array_values($runs);
  }

  public static function buildLevenshteinDifferenceString($o, $n) {
    $olt = strlen($o);
    $nlt = strlen($n);

    if (!$olt) {
      return str_repeat('i', $nlt);
    }

    if (!$nlt) {
      return str_repeat('d', $olt);
    }

    if ($o === $n) {
      return str_repeat('s', $olt);
    }

    $ov = str_split($o);
    $nv = str_split($n);

    return id(new PhutilEditDistanceMatrix())
      ->setComputeString(true)
      ->setAlterCost(0.001)
      ->setReplaceCost(2)
      ->setMaximumLength(80)
      ->setSequences($ov, $nv)
      ->getEditString();
  }

  public static function generateUTF8IntralineDiff($o, $n) {
    if (!strlen($o) || !strlen($n)) {
      return array(
        array(array(0, strlen($o))),
        array(array(0, strlen($n)))
      );
    }

    // Breaking both the strings into their component characters
    $old_characters = phutil_utf8v($o);
    $new_characters = phutil_utf8v($n);

    $old_count = count($old_characters);
    $new_count = count($new_characters);

    $prefix_match_length = 0;
    $suffix_match_length = 0;

    // Prefix matching.
    for ($i = 0; $i < $old_count; $i++) {
      if ($old_characters[$i] != $new_characters[$i]) {
        $prefix_match_length = $i;
        break;
      }
    }

    // Return no change.
    if ($old_count == $new_count && $i == $old_count) {
      return array(
               array(array(0, strlen($o))),
               array(array(0, strlen($n)))
             );
    }

    // Suffix Matching.
    $i = $old_count - 1;
    $j = $new_count - 1;

    while ($i >= 0 && $j >= 0) {
      if ($old_characters[$i] != $new_characters[$j]) {
        break;
      }

      $i--;
      $j--;
      $suffix_match_length++;

    }

    // Just a temporary fix for the edge cases where, the strings differ
    // only at beginnning, only in the end and both at the beginning and end.
    if (!$prefix_match_length || !$suffix_match_length) {
      return array(
               array(array(1, strlen($o))),
               array(array(1, strlen($n)))
             );
    }

    $old_length = strlen($o);
    $new_length = strlen($n);

    return array(
      array(
        array(0, $prefix_match_length),
        array(1, $old_length - $prefix_match_length - $suffix_match_length),
        array(0, $suffix_match_length),
      ),
      array(
        array(0, $prefix_match_length),
        array(1, $new_length - $prefix_match_length - $suffix_match_length),
        array(0, $suffix_match_length),
      )
    );

  }

}
