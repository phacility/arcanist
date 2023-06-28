<?php

final class ArcanistSimpleSymbolRefInspector
  extends ArcanistRefInspector {

  private $templateRef;

  protected function newInspectors() {
    $refs = id(new PhutilClassMapQuery())
      ->setAncestorClass('ArcanistSimpleSymbolRef')
      ->execute();

    $inspectors = array();
    foreach ($refs as $ref) {
      $inspectors[] = id(new self())
        ->setTemplateRef($ref);
    }

    return $inspectors;
  }

  public function setTemplateRef(ArcanistSimpleSymbolRef $template_ref) {
    $this->templateRef = $template_ref;
    return $this;
  }

  public function getTemplateRef() {
    return $this->templateRef;
  }

  public function getInspectFunctionName() {
    return $this->getTemplateRef()->getSimpleSymbolInspectFunctionName();
  }

  public function newInspectRef(array $argv) {
    if (count($argv) !== 1) {
      throw new PhutilArgumentUsageException(
        pht(
          'Expected exactly one argument to "%s(...)" with a symbol.',
          $this->getInspectFunctionName()));
    }

    return id(clone $this->getTemplateRef())
      ->setSymbol($argv[0]);
  }

}
