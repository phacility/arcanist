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

  protected function writeToStorage($values) {
    $content = id(new PhutilJSON())
      ->encodeFormatted($values);

    $path = $this->path;

    // If the containing directory does not exist yet, create it.
    //
    // This is expected when (for example) you first write to project
    // configuration in a Git working copy: the ".git/arc" directory will
    // not exist yet.

    $dir = dirname($path);
    if (!Filesystem::pathExists($dir)) {
      Filesystem::createDirectory($dir, 0755, $recursive = true);
    }

    Filesystem::writeFile($path, $content);
  }

}
