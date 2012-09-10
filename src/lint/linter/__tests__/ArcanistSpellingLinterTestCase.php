<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
