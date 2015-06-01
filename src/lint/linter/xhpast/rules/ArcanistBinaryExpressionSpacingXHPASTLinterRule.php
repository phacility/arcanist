<?php

final class ArcanistBinaryExpressionSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 27;

  public function getLintName() {
    return pht('Space Around Binary Operator');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $expressions = $root->selectDescendantsOfType('n_BINARY_EXPRESSION');

    foreach ($expressions as $expression) {
      $operator = $expression->getChildByIndex(1);
      $operator_value = $operator->getConcreteString();
      list($before, $after) = $operator->getSurroundingNonsemanticTokens();

      $replace = null;
      if (empty($before) && empty($after)) {
        $replace = " {$operator_value} ";
      } else if (empty($before)) {
        $replace = " {$operator_value}";
      } else if (empty($after)) {
        $replace = "{$operator_value} ";
      }

      if ($replace !== null) {
        $this->raiseLintAtNode(
          $operator,
          pht(
            'Convention: logical and arithmetic operators should be '.
            'surrounded by whitespace.'),
          $replace);
      }
    }

    $tokens = $root->selectTokensOfType(',');
    foreach ($tokens as $token) {
      $next = $token->getNextToken();
      switch ($next->getTypeName()) {
        case ')':
        case 'T_WHITESPACE':
          break;
        default:
          $this->raiseLintAtToken(
            $token,
            pht('Convention: comma should be followed by space.'),
            ', ');
          break;
      }
    }

    $tokens = $root->selectTokensOfType('T_DOUBLE_ARROW');
    foreach ($tokens as $token) {
      $prev = $token->getPrevToken();
      $next = $token->getNextToken();

      $prev_type = $prev->getTypeName();
      $next_type = $next->getTypeName();

      $prev_space = ($prev_type === 'T_WHITESPACE');
      $next_space = ($next_type === 'T_WHITESPACE');

      $replace = null;
      if (!$prev_space && !$next_space) {
        $replace = ' => ';
      } else if ($prev_space && !$next_space) {
        $replace = '=> ';
      } else if (!$prev_space && $next_space) {
        $replace = ' =>';
      }

      if ($replace !== null) {
        $this->raiseLintAtToken(
          $token,
          pht('Convention: double arrow should be surrounded by whitespace.'),
          $replace);
      }
    }

    $parameters = $root->selectDescendantsOfType('n_DECLARATION_PARAMETER');
    foreach ($parameters as $parameter) {
      if ($parameter->getChildByIndex(2)->getTypeName() == 'n_EMPTY') {
        continue;
      }

      $operator = head($parameter->selectTokensOfType('='));
      $before = $operator->getNonsemanticTokensBefore();
      $after = $operator->getNonsemanticTokensAfter();

      $replace = null;
      if (empty($before) && empty($after)) {
        $replace = ' = ';
      } else if (empty($before)) {
        $replace = ' =';
      } else if (empty($after)) {
        $replace = '= ';
      }

      if ($replace !== null) {
        $this->raiseLintAtToken(
          $operator,
          pht(
            'Convention: logical and arithmetic operators should be '.
            'surrounded by whitespace.'),
          $replace);
      }
    }
  }

}
