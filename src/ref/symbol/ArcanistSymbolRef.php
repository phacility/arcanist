<?php

abstract class ArcanistSymbolRef
  extends ArcanistRef {

  private $symbol;

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
