<?php

final class ArcanistRevisionSymbolRef
  extends ArcanistSymbolRef {

  public function getRefDisplayName() {
    return pht('Revision Symbol "%s"', $this->getSymbol());
  }

  protected function resolveSymbol($symbol) {
    $matches = null;

    if (!preg_match('/^[Dd]?([1-9]\d*)\z/', $symbol, $matches)) {
      throw new PhutilArgumentUsageException(
        pht(
          'The format of revision symbol "%s" is unrecognized. '.
          'Expected a revision monogram like "D123", or a '.
          'revision ID like "123".',
          $symbol));
    }

    return (int)$matches[1];
  }

}
