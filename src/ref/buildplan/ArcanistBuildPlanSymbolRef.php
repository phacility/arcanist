<?php

final class ArcanistBuildPlanSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Build Plan Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPHIDType() {
    return 'HMCP';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'harbormaster.buildplan.search';
  }

  public function getSimpleSymbolConduitSearchAttachments() {
    return array();
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'buildplan';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistBuildPlanRef();
  }

}
