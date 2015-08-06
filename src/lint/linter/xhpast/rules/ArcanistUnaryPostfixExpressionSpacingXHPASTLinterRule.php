<?php

final class ArcanistUnaryPostfixExpressionSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 75;

  public function getLintName() {
    return pht('Space Before Unary Postfix Operator');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_UNARY_POSTFIX_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(1, 'n_OPERATOR');
      $operator_value = $operator->getConcreteString();
      list($before, $after) = $operator->getSurroundingNonsemanticTokens();

      if (!empty($before)) {
        $leading_text = implode('', mpull($before, 'getValue'));

        $this->raiseLintAtOffset(
          $operator->getOffset() - strlen($leading_text),
          pht('Unary postfix operators should not be prefixed by whitespace.'),
          $leading_text,
          '');
      }
    }
  }

}
