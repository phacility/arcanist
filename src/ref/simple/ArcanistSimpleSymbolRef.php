<?php

abstract class ArcanistSimpleSymbolRef
  extends ArcanistSymbolRef {

  private $type;

  const TYPE_ID = 'id';
  const TYPE_PHID = 'phid';

  final protected function newCacheKeyParts() {
    return array(
      sprintf('type(%s)', $this->type),
    );
  }

  final public function getSymbolType() {
    return $this->type;
  }

  final protected function resolveSymbol($symbol) {
    $matches = null;

    $prefix_pattern = $this->getSimpleSymbolPrefixPattern();
    if ($prefix_pattern === null) {
      $prefix_pattern = '';
    }

    $id_pattern = '(^'.$prefix_pattern.'([1-9]\d*)\z)';

    $is_id = preg_match($id_pattern, $symbol, $matches);
    if ($is_id) {
      $this->type = self::TYPE_ID;
      return (int)$matches[1];
    }

    $phid_type = $this->getSimpleSymbolPHIDType();
    $phid_type = preg_quote($phid_type);
    $phid_pattern = '(^PHID-'.$phid_type.'-\S+\z)';
    $is_phid = preg_match($phid_pattern, $symbol, $matches);
    if ($is_phid) {
      $this->type = self::TYPE_PHID;
      return $matches[0];
    }

    throw new PhutilArgumentUsageException(
      pht(
        'The format of symbol "%s" is unrecognized. Expected a '.
        'monogram like "X123", or an ID like "123", or a PHID.',
        $symbol));
  }

  protected function getSimpleSymbolPrefixPattern() {
    return null;
  }

  abstract protected function getSimpleSymbolPHIDType();
  abstract public function getSimpleSymbolConduitSearchMethodName();
  abstract public function getSimpleSymbolInspectFunctionName();

  public function getSimpleSymbolConduitSearchAttachments() {
    return array();
  }

}
