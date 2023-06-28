<?php

final class ArcanistRevisionSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Revision Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPrefixPattern() {
    return '[Dd]?';
  }

  protected function getSimpleSymbolPHIDType() {
    return 'DREV';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'differential.revision.search';
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'revision';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistRevisionRef();
  }

}
