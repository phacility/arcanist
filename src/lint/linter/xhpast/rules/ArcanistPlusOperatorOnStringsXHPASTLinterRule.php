<?php

final class ArcanistPlusOperatorOnStringsXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 21;

  public function getLintName() {
    return pht('Not String Concatenation');
  }

  public function process(XHPASTNode $root) {
    $binops = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($binops as $binop) {
      $op = $binop->getChildByIndex(1);
      if ($op->getConcreteString() !== '+') {
        continue;
      }

      $left = $binop->getChildByIndex(0);
      $right = $binop->getChildByIndex(2);

      if ($left->getTypeName() === 'n_STRING_SCALAR' ||
          $right->getTypeName() === 'n_STRING_SCALAR') {
        $this->raiseLintAtNode(
          $binop,
          pht(
            "In PHP, '%s' is the string concatenation operator, not '%s'. ".
            "This expression uses '+' with a string literal as an operand.",
            '.',
            '+'));
      }
    }
  }

}
