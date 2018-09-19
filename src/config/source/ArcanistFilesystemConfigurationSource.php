<?php

abstract class ArcanistFilesystemConfigurationSource
  extends ArcanistDictionaryConfigurationSource {

  private $path;

  public function __construct($path) {
    $this->path = $path;

    $values = array();
    if (Filesystem::pathExists($path)) {
      $contents = Filesystem::readFile($path);
      if (strlen(trim($contents))) {
        $values = phutil_json_decode($contents);
      }
    }

    $values = $this->didReadFilesystemValues($values);

    parent::__construct($values);
  }

  public function getPath() {
    return $this->path;
  }

  public function getSourceDisplayName() {
    return pht('%s (%s)', $this->getFileKindDisplayName(), $this->getPath());
  }

  abstract public function getFileKindDisplayName();

  protected function didReadFilesystemValues(array $values) {
    return $values;
  }

}