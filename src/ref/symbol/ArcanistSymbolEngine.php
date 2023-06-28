<?php

final class ArcanistSymbolEngine
  extends Phobject {

  private $workflow;
  private $refs = array();

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function loadRevisionForSymbol($symbol) {
    $refs = $this->loadRevisionsForSymbols(array($symbol));
    return head($refs)->getObject();
  }

  public function loadRevisionsForSymbols(array $symbols) {
    return $this->loadRefsForSymbols(
      new ArcanistRevisionSymbolRef(),
      $symbols);
  }

  public function loadUserForSymbol($symbol) {
    $refs = $this->loadUsersForSymbols(array($symbol));
    return head($refs)->getObject();
  }

  public function loadUsersForSymbols(array $symbols) {
    return $this->loadRefsForSymbols(
      new ArcanistUserSymbolRef(),
      $symbols);
  }

  public function loadCommitForSymbol($symbol) {
    $refs = $this->loadCommitsForSymbols(array($symbol));
    return head($refs)->getObject();
  }

  public function loadCommitsForSymbols(array $symbols) {
    return $this->loadRefsForSymbols(
      new ArcanistCommitSymbolRef(),
      $symbols);
  }

  public function loadFileForSymbol($symbol) {
    $refs = $this->loadFilesForSymbols(array($symbol));
    return head($refs)->getObject();
  }

  public function loadFilesForSymbols(array $symbols) {
    return $this->loadRefsForSymbols(
      new ArcanistFileSymbolRef(),
      $symbols);
  }

  public function loadPasteForSymbol($symbol) {
    $refs = $this->loadPastesForSymbols(array($symbol));
    return head($refs)->getObject();
  }

  public function loadPastesForSymbols(array $symbols) {
    return $this->loadRefsForSymbols(
      new ArcanistPasteSymbolRef(),
      $symbols);
  }

  public function loadRefsForSymbols(
    ArcanistSymbolRef $template,
    array $symbols) {

    $refs = array();
    $load_refs = array();
    foreach ($symbols as $symbol) {
      $ref = id(clone $template)
        ->setSymbol($symbol);

      $ref_key = $ref->getSymbolEngineCacheKey();
      if (!isset($this->refs[$ref_key])) {
        $this->refs[$ref_key] = $ref;
        $load_refs[] = $ref;
      }

      $refs[$symbol] = $ref;
    }

    $workflow = $this->getWorkflow();
    if ($load_refs) {
      $workflow->loadHardpoints($refs, ArcanistSymbolRef::HARDPOINT_OBJECT);
    }

    return $refs;
  }

}
