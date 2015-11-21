<?php

final class ArcanistNestedNamespacesXHPASTLinterRule
  extends ArcanistXHPASTLinterRule {

  const ID = 90;

  public function getLintName() {
    return pht('Nested `%s` Statements', 'namespace');
  }

  public function process(XHPASTNode $root) {
    $namespaces = $root->selectDescendantsOfType('n_NAMESPACE');

    foreach ($namespaces as $namespace) {
      $nested_namespaces = $namespace->selectDescendantsOfType('n_NAMESPACE');

      foreach ($nested_namespaces as $nested_namespace) {
        $this->raiseLintAtNode(
          $nested_namespace,
          pht(
            '`%s` declarations cannot be nested. '.
            'This construct will cause a PHP fatal error.',
            'namespace'));
      }
    }
  }

}
