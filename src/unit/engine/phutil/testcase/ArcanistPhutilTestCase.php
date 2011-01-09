<?php

/*
 * Copyright 2011 Facebook, Inc.
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

abstract class ArcanistPhutilTestCase {

  private $runningTest;
  private $results = array();

  final public function __construct() {

  }

  final protected function assertEqual($expect, $result, $message = null) {
    if ($expect === $result) {
      return;
    }

    if (is_array($expect)) {
      $expect = print_r($expect, true);
    }

    if (is_array($result)) {
      $result = print_r($result, true);
    }

    $message = "Values {$expect} and {$result} differ: {$message}";
    $this->failTest($message);
    throw new ArcanistPhutilTestTerminatedException();
  }

  final protected function assertFailure($message) {
    $this->failTest($message);
    throw new ArcanistPhutilTestTerminatedException();
  }

  final private function failTest($reason) {
    $result = new ArcanistUnitTestResult();
    $result->setName($this->runningTest);
    $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
    $result->setUserData($reason);
    $this->results[] = $result;
  }

  final private function passTest($reason) {
    $result = new ArcanistUnitTestResult();
    $result->setName($this->runningTest);
    $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
    $result->setUserData($reason);
    $this->results[] = $result;
  }

  final public function run() {
    $this->results = array();

    $reflection = new ReflectionClass($this);
    foreach ($reflection->getMethods() as $method) {
      $name = $method->getName();
      if (preg_match('/^test/', $name)) {
        $this->runningTest = $name;
        try {
          call_user_func_array(
            array($this, $name),
            array());
          $this->passTest("All assertions passed.");
        } catch (ArcanistPhutilTestTerminatedException $ex) {
          // Continue with the next test.
        } catch (Exception $ex) {
          $this->failTest($ex->getMessage());
        }
      }
    }

    return $this->results;
  }

}
