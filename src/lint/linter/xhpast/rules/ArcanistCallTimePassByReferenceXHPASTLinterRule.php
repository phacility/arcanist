<?php

final class ArcanistCallTimePassByReferenceXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 53;

  public function getLintName() {
    return pht('Call-Time Pass-By-Reference');
  }

  public function process(XHPASTNode $root) {
    $nodes = $root->selectDescendantsOfType('n_CALL_PARAMETER_LIST');

    foreach ($nodes as $node) {
      $parameters = $node->getChildrenOfType('n_VARIABLE_REFERENCE');

      foreach ($parameters as $parameter) {
        $this->raiseLintAtNode(
          $parameter,
          pht('Call-time pass-by-reference calls are prohibited.'));
      }
    }
  }

}
