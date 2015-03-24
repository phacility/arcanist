<?php

final class ArcanistSpellingLinterTestCase extends ArcanistLinterTestCase {

  public function testLinter() {
    $linter = new ArcanistSpellingLinter();
    $linter->addPartialWordRule('supermn', 'superman');
    $linter->addExactWordRule('batmn', 'batman');

    $this->executeTestsInDirectory(
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
