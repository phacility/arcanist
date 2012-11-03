<?php

/**
 * Test cases for @{class:ArcanistSpellingLinter}.
 *
 * @group testcase
 */
final class ArcanistSpellingLinterTestCase extends ArcanistLinterTestCase {

  public function testSpellingLint() {
    $linter = new ArcanistSpellingLinter();
    $linter->removeLintRule('acc'.'out');
    $linter->addPartialWordRule('supermn', 'superman');
    $linter->addWholeWordRule('batmn', 'batman');
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath(__FILE__);
    return $this->executeTestsInDirectory(
      dirname(__FILE__).'/spelling/',
      $linter,
      $working_copy);
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
