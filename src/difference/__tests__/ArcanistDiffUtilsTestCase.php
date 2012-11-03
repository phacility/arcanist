<?php

/**
 * Test cases for @{class:ArcanistDiffUtils}.
 *
 * @group testcase
 */
final class ArcanistDiffUtilsTestCase extends ArcanistTestCase {
  public function testLevenshtein() {
    $tests = array(
      array(
        'a',
        'b',
        'x'
      ),
      array(
        'kalrmr(array($b))',
        'array($b)',
        'dddddddssssssssds'
      ),
      array(
        'array($b)',
        'kalrmr(array($b))',
        'iiiiiiissssssssis'
      ),
      array(
        'zkalrmr(array($b))z',
        'xarray($b)x',
        'dddddddxsssssssssdx'
      ),
      array(
        'xarray($b)x',
        'zkalrmr(array($b))z',
        'iiiiiiixsssssssssix'
      ),
      array(
        'abcdefghi',
        'abcdefghi',
        'sssssssss'
      ),
      array(
        'abcdefghi',
        'abcdefghijkl',
        'sssssssssiii'
      ),
      array(
        'abcdefghijkl',
        'abcdefghi',
        'sssssssssddd'
      ),
      array(
        'xyzabcdefghi',
        'abcdefghi',
        'dddsssssssss'
      ),
      array(
        'abcdefghi',
        'xyzabcdefghi',
        'iiisssssssss'
      ),
      array(
        'abcdefg',
        'abxdxfg',
        'ssxsxss'
      ),
      array(
        'private function a($a, $b) {',
        'public function and($b, $c) {',
        'siixsdddxsssssssssssiissxsssxsss'
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
        ArcanistDiffUtils::buildLevenshteinDifferenceString($test[0], $test[1])
      );
    }
  }
}
