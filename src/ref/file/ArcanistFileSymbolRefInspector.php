<?php

final class ArcanistFileSymbolRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'file';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "file(...)" with a '.
          'file symbol.'));
    }

    return id(new ArcanistFileSymbolRef())
      ->setSymbol($argv[0]);
  }

}
