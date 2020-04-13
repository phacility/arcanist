<?php

final class ArcanistFileSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('File Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPrefixPattern() {
    return '[Ff]?';
  }

  protected function getSimpleSymbolPHIDType() {
    return 'FILE';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'file.search';
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'file';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistFileRef();
  }

}
