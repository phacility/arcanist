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
 * Manages unit test execution.
 *
 * @group unit
 */
abstract class ArcanistBaseUnitTestEngine {

  private $workingCopy;
  private $paths;
  private $skipFiles;
  private $arguments = array();
  protected $diffID;
  private $enableAsyncTests;
  private $enableCoverage;

  final public function __construct() {

  }

  final public function setWorkingCopy(
    ArcanistWorkingCopyIdentity $working_copy) {

    $this->workingCopy = $working_copy;
    return $this;
  }

  final public function getWorkingCopy() {
    return $this->workingCopy;
  }

  final public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  final public function getPaths() {
    return $this->paths;
  }

  final public function setSkipFiles(array $paths) {
    $this->skipFiles = $paths;
    return $this;
  }

  final public function getSkipFiles() {
    return $this->skipFiles;
  }

  final public function setArguments(array $arguments) {
    $this->arguments = $arguments;
    return $this;
  }

  final public function getArgument($key, $default = null) {
    return idx($this->arguments, $key, $default);
  }

  final public function setEnableAsyncTests($enable_async_tests) {
    $this->enableAsyncTests = $enable_async_tests;
    return $this;
  }

  final public function getEnableAsyncTests() {
    return $this->enableAsyncTests;
  }

  final public function setEnableCoverage($enable_coverage) {
    $this->enableCoverage = $enable_coverage;
    return $this;
  }

  final public function getEnableCoverage() {
    return $this->enableCoverage;
  }

  abstract public function run();

  /**
   * This function is called after run() when the diff is created
   * Child classes should override this function if they want to
   * do more with the diff ID.
   */
  public function setDifferentialDiffID($id) {
    $this->diffID = $id;
    return $this;
  }

  /**
   * Modify the return value of this function in the child class, if
   * you do not need to echo the test results after all the tests have
   * been run. This is the case for example when the child class
   * prints the tests results while the tests are running.
   */
  public function shouldEchoTestResults() {
    return true;
  }
}
