<?php

final class ArcanistGitWorkingCopy
  extends ArcanistWorkingCopy {

  public function getMetadataDirectory() {
    return $this->getPath('.git');
  }

  protected function newWorkingCopyFromDirectories(
    $working_directory,
    $ancestor_directory) {

    if (!Filesystem::pathExits($ancestor_directory.'/.git')) {
      return null;
    }

    return new self();
  }

}

