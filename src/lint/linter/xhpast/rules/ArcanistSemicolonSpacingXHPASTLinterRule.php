<?php

final class ArcanistSemicolonSpacingXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 43;

  public function getLintName() {
    return pht('Semicolon Spacing');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->selectTokensOfType(';');

    foreach ($tokens as $token) {
      $prev = $token->getPrevToken();

      if ($prev->isAnyWhitespace()) {
        $this->raiseLintAtToken(
          $prev,
          pht('Space found before semicolon.'),
          '');
      }
    }
  }

}
