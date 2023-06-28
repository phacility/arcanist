<?php

final class ArcanistUserSymbolRef
  extends ArcanistSymbolRef {

  private $type;

  const TYPE_ID = 'id';
  const TYPE_PHID = 'phid';
  const TYPE_USERNAME = 'username';
  const TYPE_FUNCTION = 'function';

  public function getRefDisplayName() {
    return pht('User Symbol "%s"', $this->getSymbol());
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

    $is_id = preg_match('/^([1-9]\d*)\z/', $symbol, $matches);
    if ($is_id) {
      $this->type = self::TYPE_ID;
      return (int)$matches[1];
    }

    $is_phid = preg_match('/^PHID-USER-\S+\z/', $symbol, $matches);
    if ($is_phid) {
      $this->type = self::TYPE_PHID;
      return $matches[0];
    }

    $is_function = preg_match('/^\S+\(\s*\)\s*\z/', $symbol, $matches);
    if ($is_function) {
      $this->type = self::TYPE_FUNCTION;
      return $matches[0];
    }

    $is_username = preg_match('/^@?(\S+)\z/', $symbol, $matches);
    if ($is_username) {
      $this->type = self::TYPE_USERNAME;
      return $matches[1];
    }

    throw new PhutilArgumentUsageException(
      pht(
        'The format of user symbol "%s" is unrecognized. Expected a '.
        'username like "alice" or "@alice", or a user PHID, or a user '.
        'ID, or a special function like "viewer()".',
        $symbol));
  }

}
