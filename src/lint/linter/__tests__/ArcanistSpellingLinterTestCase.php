<?php

final class ArcanistSpellingLinterTestCase extends ArcanistLinterTestCase {

  protected function getLinter() {
    return parent::getLinter()
      ->addPartialWordRule('supermn', 'superman')
      ->addExactWordRule('batmn', 'batman');
  }

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/spelling/');
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
