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

abstract class ArcanistLinter {

  protected $paths  = array();
  protected $data   = array();
  protected $engine;
  protected $activePath;
  protected $messages = array();

  protected $stopAllLinters = false;

  private $customSeverityMap = array();

  public function setCustomSeverityMap(array $map) {
    $this->customSeverityMap = $map;
    return $this;
  }

  public function getActivePath() {
    return $this->activePath;
  }

  public function stopAllLinters() {
    $this->stopAllLinters = true;
    return $this;
  }

  public function didStopAllLinters() {
    return $this->stopAllLinters;
  }

  public function addPath($path) {
    $this->paths[$path] = $path;
    return $this;
  }

  public function getPaths() {
    return array_values($this->paths);
  }

  public function addData($path, $data) {
    $this->data[$path] = $data;
    return $this;
  }

  protected function getData($path) {
    if (!array_key_exists($path, $this->data)) {
      throw new Exception("Data is not provided for path '{$path}'!");
    }
    return $this->data[$path];
  }

  public function setEngine($engine) {
    $this->engine = $engine;
    return $this;
  }

  protected function getEngine() {
    return $this->engine;
  }

  public function getLintMessageFullCode($short_code) {
    return $this->getLinterName().$short_code;
  }

  public function getLintMessageSeverity($code) {
    $map = $this->customSeverityMap;
    if (isset($map[$code])) {
      return $map[$code];
    }

    $map = $this->getLintSeverityMap();
    if (isset($map[$code])) {
      return $map[$code];
    }

    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getLintMessageName($code) {
    $map = $this->getLintNameMap();
    if (isset($map[$code])) {
      return $map[$code];
    }
    return "Unknown lint message!";
  }

  protected function addLintMessage(ArcanistLintMessage $message) {
    $this->messages[] = $message;
    return $message;
  }

  public function getLintMessages() {
    return $this->messages;
  }

  protected function raiseLintAtLine(
    $line,
    $char,
    $code,
    $desc,
    $original = null,
    $replacement = null) {

    $dict = array(
      'path'          => $this->getActivePath(),
      'line'          => $line,
      'char'          => $char,
      'code'          => $this->getLintMessageFullCode($code),
      'severity'      => $this->getLintMessageSeverity($code),
      'name'          => $this->getLintMessageName($code),
      'description'   => $desc,
    );

    if ($original !== null) {
      $dict['original'] = $original;
    }
    if ($replacement !== null) {
      $dict['replacement'] = $replacement;
    }

    return $this->addLintMessage(ArcanistLintMessage::newFromDictionary($dict));
  }

  protected function raiseLintAtPath(
    $code,
    $desc) {

    $path = $this->getActivePath();
    return $this->raiseLintAtLine(null, null, $code, $desc, null, null);
  }

  protected function raiseLintAtOffset(
    $offset,
    $code,
    $desc,
    $original = null,
    $replacement = null) {

    $path = $this->getActivePath();
    $engine = $this->getEngine();
    list($line, $char) = $engine->getLineAndCharFromOffset($path, $offset);

    return $this->raiseLintAtLine(
      $line + 1,
      $char + 1,
      $code,
      $desc,
      $original,
      $replacement);
  }

  public function willLintPath($path) {
    $this->stopAllLinters = false;
    $this->activePath = $path;
  }

  abstract public function willLintPaths(array $paths);
  abstract public function lintPath($path);
  abstract public function getLinterName();
  abstract public function getLintSeverityMap();
  abstract public function getLintNameMap();

}
