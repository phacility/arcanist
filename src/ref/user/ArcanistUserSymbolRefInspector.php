<?php

final class ArcanistUserSymbolRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'user';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "user(...)" with a '.
          'user symbol.'));
    }

    return id(new ArcanistUserSymbolRef())
      ->setSymbol($argv[0]);
  }

}
