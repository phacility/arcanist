<?php

final class ArcanistTautologicalExpressionXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 20;

  public function getLintName() {
    return pht('Tautological Expression');
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    static $operators = array(
      '-'   => true,
      '/'   => true,
      '-='  => true,
      '/='  => true,
      '<='  => true,
      '<'   => true,
      '=='  => true,
      '===' => true,
      '!='  => true,
      '!==' => true,
      '>='  => true,
      '>'   => true,
    );

    static $logical = array(
      '||'  => true,
      '&&'  => true,
    );

    foreach ($expressions as $expr) {
      $operator = $expr->getChildByIndex(1)->getConcreteString();
      if (!empty($operators[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        if ($left === $right) {
          $this->raiseLintAtNode(
            $expr,
            pht(
              'Both sides of this expression are identical, so it always '.
              'evaluates to a constant.'));
        }
      }

      if (!empty($logical[$operator])) {
        $left = $expr->getChildByIndex(0)->getSemanticString();
        $right = $expr->getChildByIndex(2)->getSemanticString();

        // NOTE: These will be null to indicate "could not evaluate".
        $left = $this->evaluateStaticBoolean($left);
        $right = $this->evaluateStaticBoolean($right);

        if (($operator === '||' && ($left === true || $right === true)) ||
            ($operator === '&&' && ($left === false || $right === false))) {
          $this->raiseLintAtNode(
            $expr,
            pht(
              'The logical value of this expression is static. '.
              'Did you forget to remove some debugging code?'));
        }
      }
    }
  }

}
