<?php

final class ArcanistUnaryPrefixExpressionSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 73;

  public function getLintName() {
    return pht('Space After Unary Prefix Operator');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_UNARY_PREFIX_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildOfType(0, 'n_OPERATOR');
      $operator_value = $operator->getConcreteString();
      list($before, $after) = $operator->getSurroundingNonsemanticTokens();

      switch (strtolower($operator_value)) {
        case 'clone':
        case 'echo':
        case 'print':
          break;

        default:
          if (!empty($after)) {
            $this->raiseLintAtOffset(
              $operator->getOffset() + strlen($operator->getConcreteString()),
              pht(
                'Unary prefix operators should not be followed by whitespace.'),
              implode('', mpull($after, 'getValue')),
              '');
          }
          break;
      }
    }
  }

}
