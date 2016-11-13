<?php

abstract class ArcanistGitHardpointLoader
  extends ArcanistHardpointLoader {

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return ($api instanceof ArcanistGitAPI);
  }

}
