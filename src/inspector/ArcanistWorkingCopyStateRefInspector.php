<?php

final class ArcanistWorkingCopyStateRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'working-copy';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "working-copy(...)" with a '.
          'commit hash.'));
    }

    $commit_hash = $argv[0];
    $commit_ref = id(new ArcanistCommitRef())
      ->setCommitHash($commit_hash);

    return id(new ArcanistWorkingCopyStateRef())
      ->setCommitRef($commit_ref);
  }

}
