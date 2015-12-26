<?php

final class ArcanistPaamayimNekudotayimSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 96;

  public function getLintName() {
    return pht('Paamayim Nekudotayim Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $double_colons = $root->selectTokensOfType('T_PAAMAYIM_NEKUDOTAYIM');

    foreach ($double_colons as $double_colon) {
      $tokens = $double_colon->getNonsemanticTokensBefore() +
                $double_colon->getNonsemanticTokensAfter();

      foreach ($tokens as $token) {
        if ($token->isAnyWhitespace()) {
          if (strpos($token->getValue(), "\n") !== false) {
            continue;
          }

          $this->raiseLintAtToken(
            $token,
            pht(
              'Unnecessary whitespace around paamayim nekudotayim '.
              '(double colon) operator.'),
            '');
        }
      }
    }
  }

}
