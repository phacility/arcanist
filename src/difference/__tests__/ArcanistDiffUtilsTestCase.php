<?php

/**
 * Test cases for @{class:ArcanistDiffUtils}.
 */
final class ArcanistDiffUtilsTestCase extends PhutilTestCase {

  public function testLevenshtein() {
    $tests = array(
      array(
        'a',
        'b',
        'x',
      ),
      array(
        'kalrmr(array($b))',
        'array($b)',
        'dddddddssssssssds',
      ),
      array(
        'array($b)',
        'kalrmr(array($b))',
        'iiiiiiissssssssis',
      ),
      array(
        'zkalrmr(array($b))z',
        'xarray($b)x',
        'dddddddxsssssssssdx',
      ),
      array(
        'xarray($b)x',
        'zkalrmr(array($b))z',
        'iiiiiiixsssssssssix',
      ),
      array(
        'abcdefghi',
        'abcdefghi',
        'sssssssss',
      ),
      array(
        'abcdefghi',
        'abcdefghijkl',
        'sssssssssiii',
      ),
      array(
        'abcdefghijkl',
        'abcdefghi',
        'sssssssssddd',
      ),
      array(
        'xyzabcdefghi',
        'abcdefghi',
        'dddsssssssss',
      ),
      array(
        'abcdefghi',
        'xyzabcdefghi',
        'iiisssssssss',
      ),
      array(
        'abcdefg',
        'abxdxfg',
        'ssxxxss',
      ),
      array(
        'private function a($a, $b) {',
        'public function and($b, $c) {',
        'siixxdddxsssssssssssiixxxxxxxsss',
      ),
      array(

        // This is a test that we correctly detect shared prefixes and suffixes
        // and don't trigger "give up, too long" mode if there's a small text
        // change in an ocean of similar text.

        '        if ('.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx) {',
        '        if('.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'.
          'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx) {',
        'ssssssssssds'.
          'ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss'.
          'ssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssssss'.
          'sssssssssssssssssssssssssssssssssssssss',
      ),
    );

    foreach ($tests as $test) {
      $this->assertEqual(
        $test[2],
        ArcanistDiffUtils::generateEditString(
          str_split($test[0]),
          str_split($test[1])),
        pht("'%s' vs '%s'", $test[0], $test[1]));
    }

    $utf8_tests = array(
      array(
        'GrumpyCat',
        "Grumpy\xE2\x98\x83at",
        'ssssssxss',
      ),
    );

    foreach ($tests as $test) {
      $this->assertEqual(
        $test[2],
        ArcanistDiffUtils::generateEditString(
          phutil_utf8v_combined($test[0]),
          phutil_utf8v_combined($test[1])),
        pht("'%s' vs '%s' (utf8)", $test[0], $test[1]));
    }
  }

  public function testGenerateUTF8IntralineDiff() {
    // Both Strings Empty.
    $left = '';
    $right = '';
    $result = array(
      array(array(0, 0)),
      array(array(0, 0)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // Left String Empty.
    $left = '';
    $right = "Grumpy\xE2\x98\x83at";
    $result = array(
      array(array(0, 0)),
      array(array(0, 11)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // Right String Empty.
    $left = "Grumpy\xE2\x98\x83at";
    $right = '';
    $result = array(
      array(array(0, 11)),
      array(array(0, 0)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // Both Strings Same
    $left = "Grumpy\xE2\x98\x83at";
    $right = "Grumpy\xE2\x98\x83at";
    $result = array(
      array(array(0, 11)),
      array(array(0, 11)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // Both Strings are different.
    $left = "Grumpy\xE2\x98\x83at";
    $right = 'Smiling Dog';
    $result = array(
      array(array(1, 11)),
      array(array(1, 11)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // String with one difference in the middle.
    $left = 'GrumpyCat';
    $right = "Grumpy\xE2\x98\x83at";
    $result = array(
      array(array(0, 6), array(1, 1), array(0, 2)),
      array(array(0, 6), array(1, 3), array(0, 2)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // Differences in middle, not connected to each other.
    $left = 'GrumpyCat';
    $right = "Grumpy\xE2\x98\x83a\xE2\x98\x83t";
    $result = array(
      array(array(0, 6), array(1, 2), array(0, 1)),
      array(array(0, 6), array(1, 7), array(0, 1)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // String with difference at the beginning.
    $left = "GrumpyC\xE2\x98\x83t";
    $right = "DrumpyC\xE2\x98\x83t";
    $result = array(
      array(array(1, 1), array(0, 10)),
      array(array(1, 1), array(0, 10)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // String with difference at the end.
    $left = "GrumpyC\xE2\x98\x83t";
    $right = "GrumpyC\xE2\x98\x83P";
    $result = array(
      array(array(0, 10), array(1, 1)),
      array(array(0, 10), array(1, 1)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // String with differences at the beginning and end.
    $left = "GrumpyC\xE2\x98\x83t";
    $right = "DrumpyC\xE2\x98\x83P";
    $result = array(
      array(array(1, 1), array(0, 9), array(1, 1)),
      array(array(1, 1), array(0, 9), array(1, 1)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));

    // This is a unicode combining character, "COMBINING DOUBLE TILDE".
    $cc = "\xCD\xA0";
    $left = 'Senor';
    $right = "Sen{$cc}or";
    $result = array(
      array(array(0, 2), array(1, 1), array(0, 2)),
      array(array(0, 2), array(1, 3), array(0, 2)),
    );
    $this->assertEqual(
      $result,
      ArcanistDiffUtils::generateIntralineDiff($left, $right));
  }

}
