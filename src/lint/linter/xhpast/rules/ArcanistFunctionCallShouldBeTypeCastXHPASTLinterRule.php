<?php

final class ArcanistFunctionCallShouldBeTypeCastXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 105;

  public function getLintName() {
    return pht('Function Call Should Be Type Cast');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    static $cast_functions = array(
      'boolval' => 'bool',
      'doubleval' => 'double',
      'floatval' => 'double',
      'intval' => 'int',
      'strval' => 'string',
    );

    $casts = $this->getFunctionCalls($root, array_keys($cast_functions));

    foreach ($casts as $cast) {
      $function_name = $cast
        ->getChildOfType(0, 'n_SYMBOL_NAME')
        ->getConcreteString();
      $cast_name = $cast_functions[strtolower($function_name)];

      $parameters  = $cast->getChildOfType(1, 'n_CALL_PARAMETER_LIST');
      $replacement = null;

      // Only suggest a replacement if the function call has exactly
      // one parameter.
      if (count($parameters->getChildren()) == 1) {
        $parameter   = $parameters->getChildByIndex(0);
        $replacement = '('.$cast_name.')'.$parameter->getConcreteString();
      }

      if (strtolower($function_name) == 'intval') {
        if (count($parameters->getChildren()) >= 2) {
          $base = $parameters->getChildByIndex(1);

          if ($base->getTypeName() != 'n_NUMERIC_SCALAR') {
            break;
          }

          if ($base->getConcreteString() != '10') {
            continue;
          }

          $parameter   = $parameters->getChildByIndex(0);
          $replacement = '('.$cast_name.')'.$parameter->getConcreteString();
        }
      }

      $this->raiseLintAtNode(
        $cast,
        pht(
          'For consistency, use `%s` (a type cast) instead of `%s` '.
          '(a function call). Function calls impose additional overhead.',
          '('.$cast_name.')',
          $function_name),
        $replacement);
    }
  }

}
