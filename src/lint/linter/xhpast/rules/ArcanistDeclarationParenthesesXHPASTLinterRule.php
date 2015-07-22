<?php

final class ArcanistDeclarationParenthesesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 38;

  public function getLintName() {
    return pht('Declaration Formatting');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $decs = $root->selectDescendantsOfTypes(array(
      'n_FUNCTION_DECLARATION',
      'n_METHOD_DECLARATION',
    ));

    foreach ($decs as $dec) {
      $params = $dec->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST');
      $tokens = $params->getTokens();

      $first = head($tokens);
      $last = last($tokens);

      $leading = $first->getNonsemanticTokensBefore();
      $leading_text = implode('', mpull($leading, 'getValue'));

      $trailing = $last->getNonsemanticTokensBefore();
      $trailing_text = implode('', mpull($trailing, 'getValue'));

      if (preg_match('/^\s+$/', $leading_text)) {
        $this->raiseLintAtOffset(
          $first->getOffset() - strlen($leading_text),
          pht(
            'Convention: no spaces before opening parenthesis in '.
            'function and method declarations.'),
          $leading_text,
          '');
      }

      if (preg_match('/^\s+$/', $trailing_text)) {
        $this->raiseLintAtOffset(
          $last->getOffset() - strlen($trailing_text),
          pht(
            'Convention: no spaces before closing parenthesis in '.
            'function and method declarations.'),
          $trailing_text,
          '');
      }
    }
  }

}
