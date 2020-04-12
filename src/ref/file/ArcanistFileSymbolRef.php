<?php

final class ArcanistFileSymbolRef
  extends ArcanistSymbolRef {

  private $type;

  const TYPE_ID = 'id';
  const TYPE_PHID = 'phid';

  public function getRefDisplayName() {
    return pht('File Symbol "%s"', $this->getSymbol());
  }

  protected function newCacheKeyParts() {
    return array(
      sprintf('type(%s)', $this->type),
    );
  }

  public function getSymbolType() {
    return $this->type;
  }

  protected function resolveSymbol($symbol) {
    $matches = null;

    $is_id = preg_match('/^[Ff]?([1-9]\d*)\z/', $symbol, $matches);
    if ($is_id) {
      $this->type = self::TYPE_ID;
      return (int)$matches[1];
    }

    $is_phid = preg_match('/^PHID-FILE-\S+\z/', $symbol, $matches);
    if ($is_phid) {
      $this->type = self::TYPE_PHID;
      return $matches[0];
    }

    throw new PhutilArgumentUsageException(
      pht(
        'The format of file symbol "%s" is unrecognized. Expected a '.
        'monogram like "F123", or an ID like "123", or a file PHID.',
        $symbol));
  }

}
