<?php

final class ArcanistLanguageConstructParenthesesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 46;

  public function getLintName() {
    return pht('Language Construct Parentheses');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfTypes(array(
      'n_INCLUDE_FILE',
      'n_ECHO_LIST',
    ));

    foreach ($nodes as $node) {
      $child = head($node->getChildren());

      if ($child->getTypeName() === 'n_PARENTHETICAL_EXPRESSION') {
        list($before, $after) = $child->getSurroundingNonsemanticTokens();

        $replace = preg_replace(
          '/^\((.*)\)$/',
          '$1',
          $child->getConcreteString());

        if (!$before) {
          $replace = ' '.$replace;
        }

        $this->raiseLintAtNode(
          $child,
          pht('Language constructs do not require parentheses.'),
          $replace);
      }
    }
  }

}
