<?php

final class ArcanistLintMessageTestCase
  extends PhutilTestCase {

  public function testMessageTrimming() {
    $map = array(
      'simple' => array(
        'old' => 'a',
        'new' => 'b',
        'old.expect' => 'a',
        'new.expect' => 'b',
        'line' => 1,
        'char' => 1,
      ),
      'prefix' => array(
        'old' => 'ever after',
        'new' => 'evermore',
        'old.expect' => ' after',
        'new.expect' => 'more',
        'line' => 1,
        'char' => 5,
      ),
      'suffix' => array(
        'old' => 'arcane archaeology',
        'new' => 'mythic archaeology',
        'old.expect' => 'arcane',
        'new.expect' => 'mythic',
        'line' => 1,
        'char' => 1,
      ),
      'both' => array(
        'old' => 'large red apple',
        'new' => 'large blue apple',
        'old.expect' => 'red',
        'new.expect' => 'blue',
        'line' => 1,
        'char' => 7,
      ),
      'prefix-newline' => array(
        'old' => "four score\nand five years ago",
        'new' => "four score\nand seven years ago",
        'old.expect' => 'five',
        'new.expect' => 'seven',
        'line' => 2,
        'char' => 5,
      ),
      'mid-newline' => array(
        'old' => 'ABA',
        'new' => 'ABBA',
        'old.expect' => '',
        'new.expect' => 'B',
        'line' => 1,
        'char' => 3,
      ),
    );

    foreach ($map as $key => $test_case) {
      $message = id(new ArcanistLintMessage())
        ->setOriginalText($test_case['old'])
        ->setReplacementText($test_case['new'])
        ->setLine(1)
        ->setChar(1);

      $actual = $message->newTrimmedMessage();

      $this->assertEqual(
        $test_case['old.expect'],
        $actual->getOriginalText(),
        pht('Original text for "%s".', $key));

      $this->assertEqual(
        $test_case['new.expect'],
        $actual->getReplacementText(),
        pht('Replacement text for "%s".', $key));

      $this->assertEqual(
        $test_case['line'],
        $actual->getLine(),
        pht('Line for "%s".', $key));

      $this->assertEqual(
        $test_case['char'],
        $actual->getChar(),
        pht('Char for "%s".', $key));
    }
  }
}
