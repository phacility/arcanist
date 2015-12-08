<?php

final class ArcanistCurlyBraceArrayIndexXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 119;

  public function getLintName() {
    return pht('Curly Brace Array Index');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $index_accesses = $root->selectDescendantsOfType('n_INDEX_ACCESS');

    foreach ($index_accesses as $index_access) {
      $tokens = $index_access->getChildByIndex(1)->getTokens();

      $left_brace = head($tokens)->getPrevToken();
      while (!$left_brace->isSemantic()) {
        $left_brace = $left_brace->getPrevToken();
      }

      $right_brace = last($tokens)->getNextToken();
      while (!$right_brace->isSemantic()) {
        $right_brace = $right_brace->getNextToken();
      }

      if ($left_brace->getValue() == '{' || $right_brace->getValue() == '}') {
        $replacement = null;
        foreach ($index_access->getTokens() as $token) {
          if ($token === $left_brace) {
            $replacement .= '[';
          } else if ($token === $right_brace) {
            $replacement .= ']';
          } else {
            $replacement .= $token->getValue();
          }
        }

        $this->raiseLintAtNode(
          $index_access,
          pht('Use `%s` instead of `%s`.', "\$x['key']", "\$x{'key'}"),
          $replacement);
      }
    }
  }

}
