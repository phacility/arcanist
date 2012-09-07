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
 * Represents the outcome of running a unit test.
 *
 * @group unit
 */
final class ArcanistUnitTestResult {

  const RESULT_PASS         = 'pass';
  const RESULT_FAIL         = 'fail';
  const RESULT_SKIP         = 'skip';
  const RESULT_BROKEN       = 'broken';
  const RESULT_UNSOUND      = 'unsound';
  const RESULT_POSTPONED    = 'postponed';

  private $namespace;
  private $name;
  private $link;
  private $result;
  private $duration;
  private $userData;
  private $extraData;
  private $coverage;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setLink($link) {
    $this->link = $link;
    return $this;
  }

  public function getLink() {
    return $this->link;
  }

  public function setResult($result) {
    $this->result = $result;
    return $this;
  }

  public function getResult() {
    return $this->result;
  }

  public function setDuration($duration) {
    $this->duration = $duration;
    return $this;
  }

  public function getDuration() {
    return $this->duration;
  }

  public function setUserData($user_data) {
    $this->userData = $user_data;
    return $this;
  }

  public function getUserData() {
    return $this->userData;
  }

  /**
   * "extra data" allows an implementation to store additional
   * key/value metadata along with the result of the test run.
   */
  public function setExtraData(array $extra_data = null) {
    $this->extraData = $extra_data;
    return $this;
  }

  public function getExtraData() {
    return $this->extraData;
  }

  public function setCoverage($coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  public function getCoverage() {
    return $this->coverage;
  }

  /**
   * Merge several coverage reports into a comprehensive coverage report.
   *
   * @param list List of coverage report strings.
   * @return string Cumulative coverage report.
   */
  public static function mergeCoverage(array $coverage) {
    if (empty($coverage)) {
      return null;
    }

    $base = reset($coverage);
    foreach ($coverage as $more_coverage) {
      $len = min(strlen($base), strlen($more_coverage));
      for ($ii = 0; $ii < $len; $ii++) {
        if ($more_coverage[$ii] == 'C') {
          $base[$ii] = 'C';
        }
      }
    }
    return $base;
  }

}
