<?php

final class ArcanistBrowseRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'browse';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "browse(...)" with a '.
          'token.'));
    }

    return id(new ArcanistBrowseRef())
      ->setToken($argv[0]);
  }

}
