<?php

final class ArcanistLambdaFuncFunctionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 68;

  public function getLintName() {
    return pht('`%s` Function', '__lambda_func');
  }

  public function process(XHPASTNode $root) {
    $function_declarations = $root
      ->selectDescendantsOfType('n_FUNCTION_DECLARATION');

    foreach ($function_declarations as $function_declaration) {
      $function_name = $function_declaration->getChildByIndex(2);

      if ($function_name->getTypeName() == 'n_EMPTY') {
        // Anonymous closure.
        continue;
      }

      if ($function_name->getConcreteString() != '__lambda_func') {
        continue;
      }

      $this->raiseLintAtNode(
        $function_declaration,
        pht(
          'Declaring a function named `%s` causes any call to %s to fail. '.
          'This is because `%s` eval-declares the function `%s`, then '.
          'modifies the symbol table so that the function is instead '.
          'named `%s`, and returns that name.',
          '__lambda_func',
          'create_function',
          'create_function',
          '__lambda_func',
          '"\0lambda_".(++$i)'));
    }
  }

}
