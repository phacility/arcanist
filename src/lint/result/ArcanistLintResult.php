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
 * A group of @{class:ArcanistLintMessage}s that apply to a file.
 *
 * @group lint
 */
final class ArcanistLintResult {

  protected $path;
  protected $data;
  protected $filePathOnDisk;
  protected $messages = array();
  protected $effectiveMessages = array();
  private $needsSort;

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function addMessage(ArcanistLintMessage $message) {
    $this->messages[] = $message;
    $this->needsSort = true;
    return $this;
  }

  public function getMessages() {
    if ($this->needsSort) {
      $this->sortAndFilterMessages();
    }
    return $this->effectiveMessages;
  }

  public function setData($data) {
    $this->data = $data;
    return $this;
  }

  public function getData() {
    return $this->data;
  }

  public function setFilePathOnDisk($file_path_on_disk) {
    $this->filePathOnDisk = $file_path_on_disk;
    return $this;
  }

  public function getFilePathOnDisk() {
    return $this->filePathOnDisk;
  }

  public function isPatchable() {
    foreach ($this->messages as $message) {
      if ($message->isPatchable()) {
        return true;
      }
    }
    return false;
  }

  private function sortAndFilterMessages() {
    $messages = $this->messages;

    foreach ($messages as $key => $message) {
      if ($message->getObsolete()) {
        unset($messages[$key]);
        continue;
      }
    }

    $map = array();
    foreach ($messages as $key => $message) {
      $map[$key] = ($message->getLine() * (2 << 12)) + $message->getChar();
    }
    asort($map);
    $messages = array_select_keys($messages, array_keys($map));

    $this->effectiveMessages = $messages;
    $this->needsSort = false;

  }

}
