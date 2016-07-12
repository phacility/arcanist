<?php

final class ArcanistInstanceOfOperatorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 69;

  public function getLintName() {
    return pht('`%s` Operator', 'instanceof');
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');

      if (strtolower($operator->getConcreteString()) != 'instanceof') {
        continue;
      }

      $object = $expression->getChildByIndex(0);

      if ($object->isStaticScalar() ||
          $object->getTypeName() == 'n_SYMBOL_NAME') {
        $this->raiseLintAtNode(
          $object,
          pht(
            '`%s` expects an object instance, constant given.',
            'instanceof'));
      }
    }
  }

}
