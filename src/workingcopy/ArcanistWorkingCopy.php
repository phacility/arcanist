<?php

abstract class ArcanistWorkingCopy
  extends Phobject {

  private $path;
  private $workingDirectory;
  private $repositoryAPI;

  public static function newFromWorkingDirectory($path) {
    $working_types = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();

    $paths = Filesystem::walkToRoot($path);
    $paths = array_reverse($paths);

    $candidates = array();
    foreach ($paths as $path_key => $ancestor_path) {
      foreach ($working_types as $working_type) {

        $working_copy = $working_type->newWorkingCopyFromDirectories(
          $path,
          $ancestor_path);
        if (!$working_copy) {
          continue;
        }

        self::configureWorkingCopy($working_copy, $ancestor_path, $path);

        $candidates[] = $working_copy;
      }
    }

    // If we've found multiple candidate working copies, we need to pick one.
    // We let the innermost working copy pick the best candidate from among
    // candidates of the same type. The rules for Git and Mercurial differ
    // slightly from the rules for Subversion.

    if ($candidates) {
      $deepest = last($candidates);

      foreach ($candidates as $key => $candidate) {
        if (get_class($candidate) != get_class($deepest)) {
          unset($candidates[$key]);
        }
      }
      $candidates = array_values($candidates);

      return $deepest->selectFromNestedWorkingCopies($candidates);
    }

    // If we haven't found a legitimate working copy that belongs to a
    // supported version control system, return a "filesystem" working copy.
    // This allows some commands to work as expected even if run outside
    // of a real working copy.

    $working_copy = new ArcanistFilesystemWorkingCopy();

    self::configureWorkingCopy($working_copy, $path, $path);

    return $working_copy;
  }

  abstract protected function newWorkingCopyFromDirectories(
    $working_directory,
    $ancestor_directory);

  final public function getPath($to_file = null) {
    return Filesystem::concatenatePaths(
      array(
        $this->path,
        $to_file,
      ));
  }

  final public function getWorkingDirectory() {
    return $this->workingDirectory;
  }

  public function getProjectConfigurationFilePath() {
    return $this->getPath('.arcconfig');
  }

  public function getLocalConfigurationFilePath() {
    if ($this->hasMetadataDirectory()) {
      return $this->getMetadataPath('arc/config');
    }

    return null;
  }

  public function getMetadataDirectory() {
    return null;
  }

  final public function hasMetadataDirectory() {
    return ($this->getMetadataDirectory() !== null);
  }

  final public function getMetadataPath($to_file = null) {
    if (!$this->hasMetadataDirectory()) {
      throw new Exception(
        pht(
          'This working copy has no metadata directory, so you can not '.
          'resolve metadata paths within it.'));
    }

    return Filesystem::concatenatePaths(
      array(
        $this->getMetadataDirectory(),
        $to_file,
      ));
  }

  protected function selectFromNestedWorkingCopies(array $candidates) {
    // Normally, the best working copy in a stack is the deepest working copy.
    // Subversion uses slightly different rules.
    return last($candidates);
  }

  final public function getRepositoryAPI() {
    if (!$this->repositoryAPI) {
      $this->repositoryAPI = $this->newRepositoryAPI();
    }

    return $this->repositoryAPI;
  }

  abstract protected function newRepositoryAPI();

  private static function configureWorkingCopy(
    ArcanistWorkingCopy $working_copy,
    $ancestor_path,
    $path) {

    $working_copy->path = $ancestor_path;
    $working_copy->workingDirectory = $path;
  }

}
