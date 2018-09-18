<?php

final class ArcanistMercurialWorkingCopy
  extends ArcanistWorkingCopy {

  public function getMetadataDirectory() {
    return $this->getPath('.hg');
  }

  protected function newWorkingCopyFromDirectories(
    $working_directory,
    $ancestor_directory) {

    if (!Filesystem::pathExits($ancestor_directory.'/.hg')) {
      return null;
    }

    return new self();
  }

}

