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

    if (!Filesystem::pathExists($ancestor_directory.'/.svn')) {
      return null;
    }

    return new self();
  }

  protected function selectFromNestedWorkingCopies(array $candidates) {
    // To select the best working copy in Subversion, we first walk up the
    // tree looking for a working copy with an ".arcconfig" file. If we find
    // one, this anchors us.

    foreach (array_reverse($candidates) as $candidate) {
      $arcconfig = $candidate->getPath('.arcconfig');
      if (Filesystem::pathExists($arcconfig)) {
        return $candidate;
      }
    }

    // If we didn't find one, we select the outermost working copy. This is
    // because older versions of Subversion (prior to 1.7) put a ".svn" file
    // in every directory, and all versions of Subversion allow you to check
    // out any subdirectory of the project as a working copy.

    // We could possibly refine this by testing if the working copy was made
    // with a recent version of Subversion and picking the deepest working copy
    // if it was, similar to Git and Mercurial.

    return head($candidates);
  }

  protected function newRepositoryAPI() {
    return new ArcanistSubversionAPI($this->getPath());
  }

}
