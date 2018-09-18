<?php

abstract class ArcanistWorkingCopy
  extends Phobject {

  private $path;
  private $workingDirectory;

  public static function newFromWorkingDirectory($path) {
    $working_types = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->execute();

    // Find the outermost directory which is under version control. We go from
    // the top because:
    //
    //   - This gives us a more reasonable behavior if you embed one repository
    //     inside another repository.
    //   - This handles old Subversion working copies correctly. Before
    //     SVN 1.7, Subversion put a ".svn/" directory in every subdirectory.

    $paths = Filesystem::walkToRoot($path);
    $paths = array_reverse($paths);
    foreach ($paths as $path_key => $ancestor_path) {
      foreach ($working_types as $working_type) {

        $working_copy = $working_type->newWorkingCopyFromDirectories(
          $path,
          $ancestor_path);
        if (!$working_copy) {
          continue;
        }

        $working_copy->path = $ancestor_path;
        $working_copy->workingDirectory = $path;

        return $working_copy;
      }
    }

    return null;
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

}
