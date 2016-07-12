<?php

final class ArcanistConcatenationOperatorXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 44;

  public function getLintName() {
    return pht('Concatenation Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->selectTokensOfType('.');
    foreach ($tokens as $token) {
      $prev = $token->getPrevToken();
      $next = $token->getNextToken();

      foreach (array('prev' => $prev, 'next' => $next) as $wtoken) {
        if ($wtoken->getTypeName() !== 'T_WHITESPACE') {
          continue;
        }

        $value = $wtoken->getValue();
        if (strpos($value, "\n") !== false) {
          // If the whitespace has a newline, it's conventional.
          continue;
        }

        $next = $wtoken->getNextToken();
        if ($next && $next->getTypeName() === 'T_COMMENT') {
          continue;
        }

        $this->raiseLintAtToken(
          $wtoken,
          pht('Convention: no spaces around string concatenation operator.'),
          '');
      }
    }
  }

}
