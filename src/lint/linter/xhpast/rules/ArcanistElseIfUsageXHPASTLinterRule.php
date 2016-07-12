<?php

final class ArcanistElseIfUsageXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 42;

  public function getLintName() {
    return pht('`elseif` Usage');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->selectTokensOfType('T_ELSEIF');

    foreach ($tokens as $token) {
      $this->raiseLintAtToken(
        $token,
        pht(
          'Usage of `%s` is preferred over `%s`.',
          'else if',
          'elseif'),
        'else if');
    }
  }

}
