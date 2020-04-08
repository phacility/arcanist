<?php

final class ArcanistFilesystemWorkingCopy
  extends ArcanistWorkingCopy {

  public function getMetadataDirectory() {
    return null;
  }

  protected function newWorkingCopyFromDirectories(
    $working_directory,
    $ancestor_directory) {
    return null;
  }

  protected function newRepositoryAPI() {
    return new ArcanistFilesystemAPI($this->getPath());
  }

  public function getProjectConfigurationFilePath() {
    // We don't support project-level configuration for "filesytem" working
    // copies because scattering random ".arcconfig" files around the
    // filesystem and having them affect program behavior is silly.
    return null;
  }

}
