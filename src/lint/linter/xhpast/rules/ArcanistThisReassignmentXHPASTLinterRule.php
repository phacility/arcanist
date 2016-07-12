<?php

final class ArcanistThisReassignmentXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 91;

  public function getLintName() {
    return pht('`%s` Reassignment', '$this');
  }

  public function process(XHPASTNode $root) {
    $binary_expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($binary_expressions as $binary_expression) {
      $operator = $binary_expression->getChildOfType(1, 'n_OPERATOR');

      if ($operator->getConcreteString() != '=') {
        continue;
      }

      $variable = $binary_expression->getChildByIndex(0);

      if ($variable->getTypeName() != 'n_VARIABLE') {
        continue;
      }

      if ($variable->getConcreteString() == '$this') {
        $this->raiseLintAtNode(
          $binary_expression,
          pht(
            '`%s` cannot be re-assigned. '.
            'This construct will cause a PHP fatal error.',
            '$this'));
      }
    }
  }

}
