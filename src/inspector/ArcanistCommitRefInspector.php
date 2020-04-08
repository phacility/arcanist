<?php

final class ArcanistCommitRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'commit';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "commit(...)" with a '.
          'commit hash.'));
    }

    return id(new ArcanistCommitRefPro())
      ->setCommitHash($argv[0]);
  }

}
