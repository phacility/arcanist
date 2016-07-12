<?php

final class ArcanistParenthesesSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 25;

  public function getLintName() {
    return pht('Spaces Inside Parentheses');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $all_paren_groups = $root->selectDescendantsOfTypes(array(
      'n_ARRAY_VALUE_LIST',
      'n_ASSIGNMENT_LIST',
      'n_CALL_PARAMETER_LIST',
      'n_DECLARATION_PARAMETER_LIST',
      'n_CONTROL_CONDITION',
      'n_FOR_EXPRESSION',
      'n_FOREACH_EXPRESSION',
    ));

    foreach ($all_paren_groups as $group) {
      $tokens = $group->getTokens();

      $token_o = array_shift($tokens);
      $token_c = array_pop($tokens);

      $nonsem_o = $token_o->getNonsemanticTokensAfter();
      $nonsem_c = $token_c->getNonsemanticTokensBefore();

      if (!$nonsem_o) {
        continue;
      }

      $raise = array();

      $string_o = implode('', mpull($nonsem_o, 'getValue'));
      if (preg_match('/^[ ]+$/', $string_o)) {
        $raise[] = array($nonsem_o, $string_o);
      }

      if ($nonsem_o !== $nonsem_c) {
        $string_c = implode('', mpull($nonsem_c, 'getValue'));
        if (preg_match('/^[ ]+$/', $string_c)) {
          $raise[] = array($nonsem_c, $string_c);
        }
      }

      foreach ($raise as $warning) {
        list($tokens, $string) = $warning;
        $this->raiseLintAtOffset(
          reset($tokens)->getOffset(),
          pht('Parentheses should hug their contents.'),
          $string,
          '');
      }
    }
  }

}
