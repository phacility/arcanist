<?php

final class ArcanistEmptyFileXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 82;

  public function getLintName() {
    return pht('Empty File');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->getTokens();

    foreach ($tokens as $token) {
      switch ($token->getTypeName()) {
        case 'T_OPEN_TAG':
        case 'T_CLOSE_TAG':
        case 'T_WHITESPACE':
          break;

        default:
          return;
      }
    }

    $this->raiseLintAtPath(
      pht("Empty files usually don't serve any useful purpose."));
  }

}
