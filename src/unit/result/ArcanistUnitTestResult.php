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

class ArcanistUnitTestResult {

  const RESULT_PASS         = 'pass';
  const RESULT_FAIL         = 'fail';
  const RESULT_SKIP         = 'skip';
  const RESULT_BROKEN       = 'broken';
  const RESULT_UNSOUND      = 'unsound';

  private $namespace;
  private $name;
  private $result;
  private $userData;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }

  public function setUserData($user_data) {
    $this->userData = $user_data;
    return $this;
  }

  public function getUserData() {
    return $this->userData;
  }

}
