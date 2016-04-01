<?php

final class ArcanistUnexpectedReturnValueXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 92;

  public function getLintName() {
    return pht('Unexpected `%s` Value', 'return');
  }

  public function getLintSeverity() {
    return ArcanistLintSeverity::SEVERITY_WARNING;
  }

  public function process(XHPASTNode $root) {
    $methods = $root->selectDescendantsOfType('n_METHOD_DECLARATION');

    foreach ($methods as $method) {
      $method_name = $method
        ->getChildOfType(2, 'n_STRING')
        ->getConcreteString();

      switch (strtolower($method_name)) {
        case '__construct':
        case '__destruct':
          $returns = $method->selectDescendantsOfType('n_RETURN');

          foreach ($returns as $return) {
            $return_value = $return->getChildByIndex(0);

            if ($return_value->getTypeName() == 'n_EMPTY') {
              continue;
            }

            if ($this->isInAnonymousFunction($return)) {
              continue;
            }

            $this->raiseLintAtNode(
              $return,
              pht(
                'Unexpected `%s` value in `%s` method.',
                'return',
                $method_name));
          }
          break;
      }
    }
  }

  private function isInAnonymousFunction(XHPASTNode $node) {
    while ($node) {
      if ($node->getTypeName() == 'n_FUNCTION_DECLARATION' &&
          $node->getChildByIndex(2)->getTypeName() == 'n_EMPTY') {
        return true;
      }

      $node = $node->getParentNode();
    }
  }

}
