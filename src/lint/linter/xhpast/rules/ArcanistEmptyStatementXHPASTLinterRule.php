<?php

final class ArcanistEmptyStatementXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 47;

  public function getLintName() {
    return pht('Empty Block Statement');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfType('n_STATEMENT_LIST');

    foreach ($nodes as $node) {
      $tokens = $node->getTokens();
      $token = head($tokens);

      if (count($tokens) <= 2) {
        continue;
      }

      // Safety check... if the first token isn't an opening brace then
      // there's nothing to do here.
      if ($token->getTypeName() != '{') {
        continue;
      }

      $only_whitespace = true;
      for ($token = $token->getNextToken();
           $token && $token->getTypeName() != '}';
           $token = $token->getNextToken()) {
        $only_whitespace = $only_whitespace && $token->isAnyWhitespace();
      }

      if (count($tokens) > 2 && $only_whitespace) {
        $this->raiseLintAtNode(
          $node,
          pht(
            "Braces for an empty block statement shouldn't ".
            "contain only whitespace."),
          '{}');
      }
    }
  }

}
