<?php

final class ArcanistConstructorParenthesesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 49;

  public function getLintName() {
    return pht('Constructor Parentheses');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfType('n_NEW');

    foreach ($nodes as $node) {
      $class  = $node->getChildByIndex(0);
      $params = $node->getChildByIndex(1);

      if ($class->getTypeName() != 'n_CLASS_DECLARATION' &&
        $params->getTypeName() == 'n_EMPTY') {

        $this->raiseLintAtNode(
          $class,
          pht('Use parentheses when invoking a constructor.'),
          $class->getConcreteString().'()');
      }
    }
  }

}
