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
      '/usr/bin/diff %C -U %s %s %s',
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
      return array(
        array(array(1, strlen($o))),
        array(array(1, strlen($n))),
      );
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

    $min = min($olt, $nlt);
    $t_start = microtime(true);

    $pre = 0;
    while ($pre < $min && $o[$pre] == $n[$pre]) {
      $pre++;
    }

    $end = 0;
    while ($end < $min && $o[($olt - 1) - $end] == $n[($nlt - 1) - $end]) {
      $end++;
    }

    if ($end + $pre >= $min) {
      $end = min($end, $min - $pre);
      $prefix = str_repeat('s', $pre);
      $suffix = str_repeat('s', $end);
      $infix = null;
      if ($olt > $nlt) {
        $infix = str_repeat('d', $olt - ($end + $pre));
      } else if ($nlt > $olt) {
        $infix = str_repeat('i', $nlt - ($end + $pre));
      }
      return $prefix.$infix.$suffix;
    }

    if ($min - ($end + $pre) > 80) {
      $max = max($olt, $nlt);
      return str_repeat('x', $min) .
          str_repeat($olt < $nlt ? 'i' : 'd', $max - $min);
    }

    $prefix = str_repeat('s', $pre);
    $suffix = str_repeat('s', $end);
    $o = substr($o, $pre, $olt - $end - $pre);
    $n = substr($n, $pre, $nlt - $end - $pre);

    $ol = strlen($o);
    $nl = strlen($n);

    $m = array_fill(0, $ol + 1, array_fill(0, $nl + 1, array()));

    $t_d = 'd';
    $t_i = 'i';
    $t_s = 's';
    $t_x = 'x';

    $m[0][0] = array(
      0,
      null);

    for ($ii = 1; $ii <= $ol; $ii++) {
      $m[$ii][0] = array(
        $ii * 1000,
        $t_d);
    }

    for ($jj = 1; $jj <= $nl; $jj++) {
      $m[0][$jj] = array(
        $jj * 1000,
        $t_i);
    }

    $ii = 1;
    do {
      $jj = 1;
      do {
        if ($o[$ii - 1] == $n[$jj - 1]) {
          $sub_t_cost = $m[$ii - 1][$jj - 1][0] + 0;
          $sub_t      = $t_s;
        } else {
          $sub_t_cost = $m[$ii - 1][$jj - 1][0] + 2000;
          $sub_t      = $t_x;
        }

        if ($m[$ii - 1][$jj - 1][1] != $sub_t) {
          $sub_t_cost += 1;
        }

        $del_t_cost = $m[$ii - 1][$jj][0] + 1000;
        if ($m[$ii - 1][$jj][1] != $t_d) {
          $del_t_cost += 1;
        }

        $ins_t_cost = $m[$ii][$jj - 1][0] + 1000;
        if ($m[$ii][$jj - 1][1] != $t_i) {
          $ins_t_cost += 1;
        }

        if ($sub_t_cost <= $del_t_cost && $sub_t_cost <= $ins_t_cost) {
          $m[$ii][$jj] = array(
            $sub_t_cost,
            $sub_t);
        } else if ($ins_t_cost <= $del_t_cost) {
          $m[$ii][$jj] = array(
            $ins_t_cost,
            $t_i);
        } else {
          $m[$ii][$jj] = array(
            $del_t_cost,
            $t_d);
        }
      } while ($jj++ < $nl);
    } while ($ii++ < $ol);

    $result = '';
    $ii = $ol;
    $jj = $nl;
    do {
      $r = $m[$ii][$jj][1];
      $result .= $r;
      switch ($r) {
        case $t_s:
        case $t_x:
          $ii--;
          $jj--;
          break;
        case $t_i:
          $jj--;
          break;
        case $t_d:
          $ii--;
          break;
      }
    } while ($ii || $jj);

    return $prefix.strrev($result).$suffix;
  }

}
