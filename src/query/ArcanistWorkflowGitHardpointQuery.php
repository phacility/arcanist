<?php

abstract class ArcanistWorkflowGitHardpointQuery
  extends ArcanistWorkflowHardpointQuery {

  final protected function canLoadHardpoint() {
    $api = $this->getRepositoryAPI();
    return ($api instanceof ArcanistGitAPI);
  }

}
