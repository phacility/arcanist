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

/**
 * Manages unit test execution.
 *
 * @group unit
 */
abstract class ArcanistBaseUnitTestEngine {

  private $workingCopy;
  private $paths;
  private $arguments = array();

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

  final public function setArguments(array $arguments) {
    $this->arguments = $arguments;
    return $this;
  }

  final public function getArgument($key, $default = null) {
    return idx($this->arguments, $key, $default);
  }

  abstract public function run();

}
