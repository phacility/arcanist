<?php

final class ArcanistPasteSymbolRef
  extends ArcanistSimpleSymbolRef {

  public function getRefDisplayName() {
    return pht('Paste Symbol "%s"', $this->getSymbol());
  }

  protected function getSimpleSymbolPrefixPattern() {
    return '[Pp]?';
  }

  protected function getSimpleSymbolPHIDType() {
    return 'PSTE';
  }

  public function getSimpleSymbolConduitSearchMethodName() {
    return 'paste.search';
  }

  public function getSimpleSymbolConduitSearchAttachments() {
    return array(
      'content' => true,
    );
  }

  public function getSimpleSymbolInspectFunctionName() {
    return 'paste';
  }

  public function newSimpleSymbolObjectRef() {
    return new ArcanistPasteRef();
  }

}
