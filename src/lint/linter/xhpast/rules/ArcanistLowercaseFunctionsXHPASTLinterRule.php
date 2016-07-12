<?php

final class ArcanistLowercaseFunctionsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 61;

  public function getLintName() {
    return pht('Lowercase Functions');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    static $builtin_functions = null;

    if ($builtin_functions === null) {
      $builtin_functions = array_fuse(
        idx(get_defined_functions(), 'internal', array()));
    }

    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');

    foreach ($function_calls as $function_call) {
      $function = $function_call->getChildByIndex(0);

      if ($function->getTypeName() != 'n_SYMBOL_NAME') {
        continue;
      }

      $function_name = $function->getConcreteString();

      if (!idx($builtin_functions, strtolower($function_name))) {
        continue;
      }

      if ($function_name != strtolower($function_name)) {
        $this->raiseLintAtNode(
          $function,
          pht('Calls to built-in PHP functions should be lowercase.'),
          strtolower($function_name));
      }
    }
  }

}
