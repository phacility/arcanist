<?php

final class ArcanistRemoteRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'remote';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "remote(...)" with a '.
          'remote name.'));
    }

    $remote_name = $argv[0];

    $workflow = $this->getWorkflow();
    $api = $workflow->getRepositoryAPI();

    $ref = $api->newRemoteRefQuery()
      ->withNames(array($remote_name))
      ->executeOne();

    if (!$ref) {
      throw new PhutilArgumentUsageException(
        pht(
          'This working copy has no remote named "%s".',
          $remote_name));
    }

    return $ref;
  }

}
