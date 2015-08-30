<?php

final class ArcanistNewlineAfterOpenTagXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 81;

  public function getLintName() {
    return pht('Newline After PHP Open Tag');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $tokens = $root->selectTokensOfType('T_OPEN_TAG');

    foreach ($tokens as $token) {
      for ($next = $token->getNextToken();
           $next;
           $next = $next->getNextToken()) {

        if ($next->getTypeName() == 'T_WHITESPACE' &&
            preg_match('/\n\s*\n/', $next->getValue())) {
          continue 2;
        }

        if ($token->getLineNumber() != $next->getLineNumber()) {
          break;
        }

        if ($next->getTypeName() == 'T_CLOSE_TAG') {
          continue 2;
        }
      }

      $next = $token->getNextToken();
      $this->raiseLintAtToken(
        $next,
        pht('`%s` should be separated from code by an empty line.', '<?php'),
        "\n".$next->getValue());
    }
  }

}
