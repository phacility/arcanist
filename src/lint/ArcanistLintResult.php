<?php

/**
 * A group of @{class:ArcanistLintMessage}s that apply to a file.
 */
final class ArcanistLintResult extends Phobject {

  protected $path;
  protected $data;
  protected $filePathOnDisk;
  protected $cacheVersion;
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

  public function setCacheVersion($version) {
    $this->cacheVersion = $version;
    return $this;
  }

  public function getCacheVersion() {
    return $this->cacheVersion;
  }

  public function isPatchable() {
    foreach ($this->messages as $message) {
      if ($message->isPatchable()) {
        return true;
      }
    }
    return false;
  }

  public function isAllAutofix() {
    foreach ($this->messages as $message) {
      if (!$message->isAutofix()) {
        return false;
      }
    }
    return true;
  }

  public function sortAndFilterMessages() {
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
