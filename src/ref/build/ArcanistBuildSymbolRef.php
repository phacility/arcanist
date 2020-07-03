<?php

final class ArcanistBuildSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Build Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPHIDType() {
    return 'HMBD';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'harbormaster.build.search';
  }

  public function getSimpleSymbolConduitSearchAttachments() {
    return array();
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'build';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistBuildRef();
  }

}
