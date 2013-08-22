<?php

/**
 * Test cases for @{class:ArcanistSpellingLinter}.
 *
 * @group testcase
 */
final class ArcanistSpellingLinterTestCase
  extends ArcanistArcanistLinterTestCase {

  public function testSpellingLint() {
    $linter = new ArcanistSpellingLinter();
    $linter->removeLintRule('acc'.'out');
    $linter->addPartialWordRule('supermn', 'superman');
    $linter->addWholeWordRule('batmn', 'batman');

    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/spelling/',
      $linter);
  }

  public function testFixLetterCase() {
    $tests = array(
      'tst' => 'test',
      'Tst' => 'Test',
      'TST' => 'TEST',
      'tSt' => null,
    );
    foreach ($tests as $case => $expect) {
      foreach (array('test', 'TEST') as $string) {
        $result = ArcanistSpellingLinter::fixLetterCase($string, $case);
        $this->assertEqual($expect, $result, $case);
      }
    }
  }

}
