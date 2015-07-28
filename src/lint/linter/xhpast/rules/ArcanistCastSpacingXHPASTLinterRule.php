<?php

final class ArcanistCastSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 66;

  public function getLintName() {
    return pht('Cast Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $cast_expressions = $root->selectDescendantsOfType('n_CAST_EXPRESSION');

    foreach ($cast_expressions as $cast_expression) {
      $cast = $cast_expression->getChildOfType(0, 'n_CAST');

      list($before, $after) = $cast->getSurroundingNonsemanticTokens();
      $after = head($after);

      if ($after) {
        $this->raiseLintAtToken(
          $after,
          pht('A cast statement must not be followed by a space.'),
          '');
      }
    }
  }

}
