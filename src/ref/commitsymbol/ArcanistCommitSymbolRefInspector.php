<?php

final class ArcanistCommitSymbolRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'symbol';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "symbol(...)" with a '.
          'commit symbol.'));
    }

    return id(new ArcanistCommitSymbolRef())
      ->setSymbol($argv[0]);
  }

}
