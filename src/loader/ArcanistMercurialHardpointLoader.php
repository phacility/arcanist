<?php

abstract class ArcanistMercurialHardpointLoader
  extends ArcanistHardpointLoader {

  public function canLoadRepositoryAPI(ArcanistRepositoryAPI $api) {
    return ($api instanceof ArcanistMercurialAPI);
  }

}
