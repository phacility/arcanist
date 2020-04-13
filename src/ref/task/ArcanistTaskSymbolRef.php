<?php

final class ArcanistTaskSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Task Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPrefixPattern() {
    return '[Tt]?';
  }

  protected function getSimpleSymbolPHIDType() {
    return 'TASK';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'maniphest.search';
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'task';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistTaskRef();
  }

}
