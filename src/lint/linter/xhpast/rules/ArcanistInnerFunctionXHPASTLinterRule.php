<?php

final class ArcanistInnerFunctionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 59;

  public function getLintName() {
    return pht('Inner Functions');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $function_decls = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');

    foreach ($function_decls as $function_declaration) {
      $inner_functions = $function_declaration
        ->selectDescendantsOfType('n_FUNCTION_DECLARATION');

      foreach ($inner_functions as $inner_function) {
        if ($inner_function->getChildByIndex(2)->getTypeName() == 'n_EMPTY') {
          // Anonymous closure.
          continue;
        }

        $this->raiseLintAtNode(
          $inner_function,
          pht('Avoid the use of inner functions.'));
      }
    }
  }

}
