<?php

final class ArcanistBuildableSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Buildable Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPHIDType() {
    return 'HMBB';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'harbormaster.buildable.search';
  }

  public function getSimpleSymbolConduitSearchAttachments() {
    return array();
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'buildable';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistBuildableRef();
  }

}
