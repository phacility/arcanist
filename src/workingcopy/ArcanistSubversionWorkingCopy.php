<?php

final class ArcanistSubversionWorkingCopy
  extends ArcanistWorkingCopy {

  public function getProjectConfigurationFilePath() {
    // In Subversion, we allow ".arcconfig" to appear at any level of the
    // filesystem between the working directory and the working copy root.

    // We allow this because Subversion repositories are hierarchical and
    // may have a "projects/xyz/" directory which is meaningfully an entirely
    // different project from "projects/abc/".

    // You can checkout "projects/" and have the ".svn/" directory appear
    // there, then change into "abc/" and expect "arc" to operate within the
    // context of the "abc/" project.

    $paths = Filesystem::walkToRoot($this->getWorkingDirectory());
    $root = $this->getPath();
    foreach ($paths as $path) {
      if (!Filesystem::isDescendant($path, $root)) {
        break;
      }

      $candidate = $path.'/.arcconfig';
      if (Filesystem::pathExists($candidate)) {
        return $candidate;
      }
    }

    return parent::getProjectConfigurationFilePath();
  }

  public function getMetadataDirectory() {
    return $this->getPath('.svn');
  }

  protected function newWorkingCopyFromDirectories(
    $working_directory,
    $ancestor_directory) {

    if (!Filesystem::pathExits($ancestor_directory.'/.svn')) {
      return null;
    }

    return id(new self());
  }


}

