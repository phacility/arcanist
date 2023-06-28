<?php

/**
 * Dumping ground for diff- and diff-algorithm-related miscellany.
 */
final class ArcanistDiffUtils extends Phobject {

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
      $new .= "\n".pht('(Old and new values are identical.)');
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
    $ol = strlen($o);
    $nl = strlen($n);

    if (($o === $n) || !$ol || !$nl) {
      return array(
        array(array(0, $ol)),
        array(array(0, $nl)),
      );
    }

    // Do a fast check for certainly-too-long inputs before splitting the
    // lines. Inputs take ~200x more memory to represent as lists than as
    // strings, so we can run out of memory quickly if we try to split huge
    // inputs. See T11744.
    $ol = strlen($o);
    $nl = strlen($n);

    $max_glyphs = 100;

    // This has some wiggle room for multi-byte UTF8 characters, and the
    // fact that we're testing the sum of the lengths of both strings. It can
    // still generate false positives for, say, Chinese text liberally
    // slathered with combining characters, but this kind of text should be
    // vitually nonexistent in real data.
    $too_many_bytes = (16 * $max_glyphs);

    if ($ol + $nl > $too_many_bytes) {
      return array(
        array(array(1, $ol)),
        array(array(1, $nl)),
      );
    }

    return self::computeIntralineEdits($o, $n, $max_glyphs);
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

  public static function generateEditString(array $ov, array $nv, $max = 80) {
    return id(new PhutilEditDistanceMatrix())
      ->setComputeString(true)
      ->setAlterCost(1 / ($max * 2))
      ->setReplaceCost(2)
      ->setMaximumLength($max)
      ->setSequences($ov, $nv)
      ->setApplySmoothing(PhutilEditDistanceMatrix::SMOOTHING_INTERNAL)
      ->getEditString();
  }

  private static function computeIntralineEdits($o, $n, $max_glyphs) {
    if (preg_match('/[\x80-\xFF]/', $o.$n)) {
      $ov = phutil_utf8v_combined($o);
      $nv = phutil_utf8v_combined($n);
      $multibyte = true;
    } else {
      $ov = str_split($o);
      $nv = str_split($n);
      $multibyte = false;
    }

    $result = self::generateEditString($ov, $nv, $max_glyphs);

    // Now we have a character-based description of the edit. We need to
    // convert into a byte-based description. Walk through the edit string and
    // adjust each operation to reflect the number of bytes in the underlying
    // character.

    $o_pos = 0;
    $n_pos = 0;
    $result_len = strlen($result);
    $o_run = array();
    $n_run = array();

    $old_char_len = 1;
    $new_char_len = 1;

    for ($ii = 0; $ii < $result_len; $ii++) {
      $c = $result[$ii];

      if ($multibyte) {
        $old_char_len = strlen($ov[$o_pos]);
        $new_char_len = strlen($nv[$n_pos]);
      }

      switch ($c) {
        case 's':
        case 'x':
          $byte_o = $old_char_len;
          $byte_n = $new_char_len;
          $o_pos++;
          $n_pos++;
          break;
        case 'i':
          $byte_o = 0;
          $byte_n = $new_char_len;
          $n_pos++;
          break;
        case 'd':
          $byte_o = $old_char_len;
          $byte_n = 0;
          $o_pos++;
          break;
      }

      if ($byte_o) {
        if ($c == 's') {
          $o_run[] = array(0, $byte_o);
        } else {
          $o_run[] = array(1, $byte_o);
        }
      }

      if ($byte_n) {
        if ($c == 's') {
          $n_run[] = array(0, $byte_n);
        } else {
          $n_run[] = array(1, $byte_n);
        }
      }
    }

    $o_run = self::collapseIntralineRuns($o_run);
    $n_run = self::collapseIntralineRuns($n_run);

    return array($o_run, $n_run);
  }

}
