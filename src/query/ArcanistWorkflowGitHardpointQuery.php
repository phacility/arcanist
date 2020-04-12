<?php

abstract class ArcanistWorkflowGitHardpointQuery
  extends ArcanistRuntimeHardpointQuery {

  final protected function canLoadHardpoint() {
    $api = $this->getRepositoryAPI();
    return ($api instanceof ArcanistGitAPI);
  }

}
