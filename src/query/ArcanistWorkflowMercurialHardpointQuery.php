<?php

abstract class ArcanistWorkflowMercurialHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  final protected function canLoadHardpoint() {
    $api = $this->getRepositoryAPI();
    return ($api instanceof ArcanistMercurialAPI);
  }

}
