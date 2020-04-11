<?php

final class ArcanistRevisionSymbolRefInspector
  extends ArcanistRefInspector {

  public function getInspectFunctionName() {
    return 'revision';
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "revision(...)" with a '.
          'revision symbol.'));
    }

    return id(new ArcanistRevisionSymbolRef())
      ->setSymbol($argv[0]);
  }

}
