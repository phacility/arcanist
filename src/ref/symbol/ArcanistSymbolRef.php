<?php

abstract class ArcanistSymbolRef
  extends ArcanistRef {

  private $symbol;
  private $cacheKey;

  const HARDPOINT_OBJECT = 'ref.symbol.object';

  protected function newHardpoints() {
    return array(
      $this->newHardpoint(self::HARDPOINT_OBJECT),
    );
  }

  final public function setSymbol($symbol) {
    $symbol = $this->resolveSymbol($symbol);

    $this->symbol = $symbol;
    return $this;
  }

  final public function getSymbol() {
    return $this->symbol;
  }

  final public function getSymbolEngineCacheKey() {
    if ($this->cacheKey === null) {
      $parts = array();
      $parts[] = sprintf('class(%s)', get_class($this));

      foreach ($this->newCacheKeyParts() as $part) {
        $parts[] = $part;
      }

      $parts[] = $this->getSymbol();

      $this->cacheKey = implode('.', $parts);
    }

    return $this->cacheKey;
  }

  protected function newCacheKeyParts() {
    return array();
  }

  final public function attachObject(ArcanistRef $object) {
    return $this->attachHardpoint(self::HARDPOINT_OBJECT, $object);
  }

  final public function getObject() {
    return $this->getHardpoint(self::HARDPOINT_OBJECT);
  }

  protected function resolveSymbol($symbol) {
    return $symbol;
  }

}
